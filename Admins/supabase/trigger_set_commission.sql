-- Add commission_percent column to system_settings (if not exists)
ALTER TABLE IF EXISTS public.system_settings
ADD COLUMN IF NOT EXISTS commission_percent numeric(5,2) DEFAULT 0;

-- Create or replace trigger function to compute commission_amount on insert
CREATE OR REPLACE FUNCTION public.trigger_set_commission_amount()
RETURNS trigger AS $$
DECLARE
    v_percent numeric := 0;
BEGIN
    IF NEW.commission_amount IS NULL THEN
        SELECT commission_percent INTO v_percent FROM public.system_settings WHERE id = 1;
        IF v_percent IS NULL THEN
            v_percent := 0;
        END IF;
        NEW.commission_amount := round((COALESCE(NEW.amount, 0) * v_percent / 100)::numeric, 2);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql VOLATILE;

-- Create trigger on payment_transactions before insert
DROP TRIGGER IF EXISTS set_commission_amount_on_insert ON public.payment_transactions;
CREATE TRIGGER set_commission_amount_on_insert
BEFORE INSERT ON public.payment_transactions
FOR EACH ROW
EXECUTE FUNCTION public.trigger_set_commission_amount();
