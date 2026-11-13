<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // =========================================================================
        // 1. TRIGGER: Verify Journal Entry Balanced upon Posting
        // =========================================================================
        DB::unprepared("
            CREATE OR REPLACE FUNCTION trg_fn_verify_journal_entry_balanced()
            RETURNS TRIGGER AS $$
            DECLARE
                v_total_debit NUMERIC(14, 2);
                v_total_credit NUMERIC(14, 2);
                v_line_count INT;
            BEGIN
                IF NEW.status = 'posted' AND (OLD.status IS DISTINCT FROM 'posted') THEN
                    SELECT 
                        COALESCE(SUM(CASE WHEN entry_type = 'debit' THEN amount ELSE 0 END), 0.00),
                        COALESCE(SUM(CASE WHEN entry_type = 'credit' THEN amount ELSE 0 END), 0.00),
                        COUNT(*)
                    INTO v_total_debit, v_total_credit, v_line_count
                    FROM journal_entry_lines
                    WHERE journal_entry_id = NEW.id;

                    IF v_line_count < 2 THEN
                        RAISE EXCEPTION 'Posted journal entry % must contain at least two lines.', NEW.entry_number;
                    END IF;

                    IF v_total_debit <> v_total_credit THEN
                        RAISE EXCEPTION 'Journal entry % is unbalanced: Total Debits (%) do not equal Total Credits (%).', 
                            NEW.entry_number, v_total_debit, v_total_credit;
                    END IF;

                    NEW.posted_at := COALESCE(NEW.posted_at, NOW());
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_verify_journal_entry_balanced ON journal_entries;
            CREATE TRIGGER trg_verify_journal_entry_balanced
                BEFORE UPDATE ON journal_entries
                FOR EACH ROW
                EXECUTE FUNCTION trg_fn_verify_journal_entry_balanced();
        ");

        // =========================================================================
        // 2. TRIGGER: Guard Journal Entry Header Mutations & Deletions
        // =========================================================================
        DB::unprepared("
            CREATE OR REPLACE FUNCTION trg_fn_guard_journal_entry_mutation()
            RETURNS TRIGGER AS $$
            DECLARE
                v_reversal_exists BOOLEAN;
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    IF OLD.status IN ('posted', 'reversed') THEN
                        RAISE EXCEPTION 'Cannot delete % journal entry % (ID: %). Posted financial records are permanent.', 
                            OLD.status, OLD.entry_number, OLD.id;
                    END IF;
                    RETURN OLD;
                END IF;

                IF TG_OP = 'UPDATE' THEN
                    IF OLD.status = 'posted' THEN
                        -- Allow ONLY transition to 'reversed' with valid reversed_by_entry_id linkage
                        IF NEW.status = 'reversed' AND OLD.reversed_by_entry_id IS NULL AND NEW.reversed_by_entry_id IS NOT NULL THEN
                            IF NEW.entry_number IS DISTINCT FROM OLD.entry_number OR
                               NEW.tenant_id IS DISTINCT FROM OLD.tenant_id OR
                               NEW.branch_id IS DISTINCT FROM OLD.branch_id OR
                               NEW.entry_date IS DISTINCT FROM OLD.entry_date OR
                               NEW.source_type IS DISTINCT FROM OLD.source_type OR
                               NEW.source_id IS DISTINCT FROM OLD.source_id OR
                               NEW.posted_at IS DISTINCT FROM OLD.posted_at OR
                               NEW.posted_by_user_id IS DISTINCT FROM OLD.posted_by_user_id THEN
                                RAISE EXCEPTION 'Modification of core fields on posted journal entry % is strictly prohibited.', OLD.entry_number;
                            END IF;

                            RETURN NEW;
                        END IF;

                        RAISE EXCEPTION 'Posted journal entry % cannot be modified. Reversal is the only permitted action.', OLD.entry_number;
                    END IF;

                    IF OLD.status = 'reversed' THEN
                        RAISE EXCEPTION 'Reversed journal entry % is permanently archived and cannot be modified.', OLD.entry_number;
                    END IF;

                    RETURN NEW;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_guard_journal_entry_mutation ON journal_entries;
            CREATE TRIGGER trg_guard_journal_entry_mutation
                BEFORE UPDATE OR DELETE ON journal_entries
                FOR EACH ROW
                EXECUTE FUNCTION trg_fn_guard_journal_entry_mutation();
        ");

        // =========================================================================
        // 3. TRIGGER: Guard Journal Entry Lines (Block modification on posted/reversed entries)
        // =========================================================================
        DB::unprepared("
            CREATE OR REPLACE FUNCTION trg_fn_guard_journal_line_mutation()
            RETURNS TRIGGER AS $$
            DECLARE
                v_target_entry_id UUID;
                v_parent_status VARCHAR(20);
            BEGIN
                IF TG_OP = 'DELETE' THEN
                    v_target_entry_id := OLD.journal_entry_id;
                ELSE
                    v_target_entry_id := NEW.journal_entry_id;
                END IF;

                -- Acquire row-level lock on parent header to prevent posting races
                SELECT status INTO v_parent_status 
                FROM journal_entries 
                WHERE id = v_target_entry_id 
                FOR UPDATE;

                IF v_parent_status IS NULL THEN
                    RAISE EXCEPTION 'Parent journal entry % does not exist.', v_target_entry_id;
                END IF;

                IF v_parent_status IN ('posted', 'reversed') THEN
                    RAISE EXCEPTION 'Cannot % line on journal entry % because it is already %.', 
                        TG_OP, v_target_entry_id, v_parent_status;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RETURN OLD;
                ELSE
                    RETURN NEW;
                END IF;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_guard_journal_line_mutation ON journal_entry_lines;
            CREATE TRIGGER trg_guard_journal_line_mutation
                BEFORE INSERT OR UPDATE OR DELETE ON journal_entry_lines
                FOR EACH ROW
                EXECUTE FUNCTION trg_fn_guard_journal_line_mutation();
        ");

        // =========================================================================
        // 4. TRIGGER: Enforce Reversal Rules and Link Bidirectional Pointers
        // =========================================================================
        DB::unprepared("
            CREATE OR REPLACE FUNCTION trg_fn_enforce_journal_reversal_rules()
            RETURNS TRIGGER AS $$
            DECLARE
                v_orig_status VARCHAR(20);
                v_orig_tenant_id UUID;
                v_orig_branch_id UUID;
                v_orig_reversed_by UUID;
                v_orig_source_type VARCHAR(50);
            BEGIN
                IF NEW.source_type = 'reversal' THEN
                    IF NEW.reverses_entry_id IS NULL THEN
                        RAISE EXCEPTION 'Reversal entry % must provide reverses_entry_id.', NEW.entry_number;
                    END IF;

                    IF NEW.reversed_by_entry_id IS NOT NULL THEN
                        RAISE EXCEPTION 'A reversal entry % cannot itself be reversed.', NEW.entry_number;
                    END IF;

                    SELECT status, tenant_id, branch_id, reversed_by_entry_id, source_type
                    INTO v_orig_status, v_orig_tenant_id, v_orig_branch_id, v_orig_reversed_by, v_orig_source_type
                    FROM journal_entries
                    WHERE id = NEW.reverses_entry_id
                    FOR UPDATE;

                    IF v_orig_status IS NULL THEN
                        RAISE EXCEPTION 'Original journal entry % does not exist.', NEW.reverses_entry_id;
                    END IF;

                    -- If original entry has already been reversed by another entry, raise exception
                    IF v_orig_reversed_by IS NOT NULL AND v_orig_reversed_by <> NEW.id THEN
                        RAISE EXCEPTION 'Original entry % has already been reversed by entry %.', 
                            NEW.reverses_entry_id, v_orig_reversed_by;
                    END IF;

                    IF v_orig_status <> 'posted' AND (v_orig_reversed_by IS NULL OR v_orig_reversed_by <> NEW.id) THEN
                        RAISE EXCEPTION 'Cannot reverse entry % because its status is % (must be posted).', 
                            NEW.reverses_entry_id, v_orig_status;
                    END IF;

                    IF v_orig_tenant_id <> NEW.tenant_id OR v_orig_branch_id <> NEW.branch_id THEN
                        RAISE EXCEPTION 'Reversal entry % must belong to the exact same tenant and branch as original entry.', 
                            NEW.entry_number;
                    END IF;

                    IF v_orig_source_type = 'reversal' THEN
                        RAISE EXCEPTION 'Cannot reverse entry % because it is itself a reversal entry.', NEW.reverses_entry_id;
                    END IF;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_enforce_journal_reversal_rules ON journal_entries;
            CREATE TRIGGER trg_enforce_journal_reversal_rules
                BEFORE INSERT OR UPDATE ON journal_entries
                FOR EACH ROW
                EXECUTE FUNCTION trg_fn_enforce_journal_reversal_rules();

            -- AFTER INSERT OR UPDATE TRIGGER to link the original entry's forward pointer when reversal is POSTED
            CREATE OR REPLACE FUNCTION trg_fn_link_journal_reversal()
            RETURNS TRIGGER AS $$
            BEGIN
                IF NEW.source_type = 'reversal' AND NEW.reverses_entry_id IS NOT NULL AND NEW.status = 'posted' THEN
                    UPDATE journal_entries
                    SET status = 'reversed',
                        reversed_by_entry_id = NEW.id,
                        updated_at = NOW()
                    WHERE id = NEW.reverses_entry_id;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_link_journal_reversal_after_insert ON journal_entries;
            DROP TRIGGER IF EXISTS trg_link_journal_reversal ON journal_entries;
            CREATE TRIGGER trg_link_journal_reversal
                AFTER INSERT OR UPDATE OF status ON journal_entries
                FOR EACH ROW
                EXECUTE FUNCTION trg_fn_link_journal_reversal();
        ");

        // =========================================================================
        // 5. TRIGGER: Guard System-Controlled Control Accounts Direct Posting
        // =========================================================================
        DB::unprepared("
            CREATE OR REPLACE FUNCTION trg_fn_guard_control_account_direct_posting()
            RETURNS TRIGGER AS $$
            DECLARE
                v_account_code VARCHAR(50);
                v_subledger VARCHAR(50);
                v_is_system BOOLEAN;
                v_source_type VARCHAR(50);
            BEGIN
                SELECT account_code, subledger_type, is_system_controlled
                INTO v_account_code, v_subledger, v_is_system
                FROM chart_of_accounts 
                WHERE id = NEW.account_id;

                SELECT source_type INTO v_source_type
                FROM journal_entries 
                WHERE id = NEW.journal_entry_id;

                IF v_subledger = 'student_fee_advances' AND v_source_type NOT IN ('advance_receipt', 'payment_receipt', 'advance_settlement', 'advance_refund', 'advance_reversal', 'reversal') THEN
                    RAISE EXCEPTION 'Account % (%) is a system-controlled sub-ledger account. Direct manual journal postings are prohibited.', 
                        v_account_code, v_subledger;
                END IF;

                IF v_subledger = 'student_credit_wallets' AND v_source_type NOT IN ('payment_receipt', 'wallet_credit', 'wallet_deduction', 'wallet_refund', 'reversal') THEN
                    RAISE EXCEPTION 'Account % (%) is a system-controlled student credit wallet account. Direct manual journal postings are prohibited.', 
                        v_account_code, v_subledger;
                END IF;

                IF v_subledger = 'accounts_receivable' AND v_source_type NOT IN ('invoice_accrual', 'payment_receipt', 'advance_settlement', 'wallet_deduction', 'invoice_cancellation', 'fee_refund', 'reversal') THEN
                    RAISE EXCEPTION 'Account % (%) is a system-controlled accounts receivable account. Direct manual journal postings are prohibited.', 
                        v_account_code, v_subledger;
                END IF;

                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;

            DROP TRIGGER IF EXISTS trg_guard_control_account_direct_posting ON journal_entry_lines;
            CREATE TRIGGER trg_guard_control_account_direct_posting
                BEFORE INSERT OR UPDATE ON journal_entry_lines
                FOR EACH ROW
                EXECUTE FUNCTION trg_fn_guard_control_account_direct_posting();
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared("
            DROP TRIGGER IF EXISTS trg_guard_control_account_direct_posting ON journal_entry_lines;
            DROP FUNCTION IF EXISTS trg_fn_guard_control_account_direct_posting();

            DROP TRIGGER IF EXISTS trg_link_journal_reversal_after_insert ON journal_entries;
            DROP FUNCTION IF EXISTS trg_fn_link_journal_reversal_after_insert();

            DROP TRIGGER IF EXISTS trg_enforce_journal_reversal_rules ON journal_entries;
            DROP FUNCTION IF EXISTS trg_fn_enforce_journal_reversal_rules();

            DROP TRIGGER IF EXISTS trg_guard_journal_line_mutation ON journal_entry_lines;
            DROP FUNCTION IF EXISTS trg_fn_guard_journal_line_mutation();

            DROP TRIGGER IF EXISTS trg_guard_journal_entry_mutation ON journal_entries;
            DROP FUNCTION IF EXISTS trg_fn_guard_journal_entry_mutation();

            DROP TRIGGER IF EXISTS trg_verify_journal_entry_balanced ON journal_entries;
            DROP FUNCTION IF EXISTS trg_fn_verify_journal_entry_balanced();
        ");
    }
};
