-- Replace the enforce_trips_integrity trigger function so it prefers NEW.company_id
-- over resolving via NEW.outlet_manager_id. Run this in your Supabase SQL editor or psql

BEGIN;

CREATE OR REPLACE FUNCTION public.enforce_trips_integrity()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
  v_company uuid;
BEGIN
  -- Priority: use provided company_id first
  IF NEW.company_id IS NOT NULL THEN
    v_company := NEW.company_id;

  -- Fallback: resolve from outlet_manager_id if provided
  ELSIF NEW.outlet_manager_id IS NOT NULL THEN
    SELECT company_id INTO v_company
    FROM public.profiles
    WHERE id = NEW.outlet_manager_id;

    IF v_company IS NULL THEN
      RAISE EXCEPTION 'Outlet manager has no company';
    END IF;

  -- Neither provided -> error
  ELSE
    RAISE EXCEPTION 'Company ID is required when no outlet_manager_id is provided';
  END IF;

  -- Validate company exists (extra safeguard)
  IF NOT EXISTS (
    SELECT 1 FROM public.companies WHERE id = v_company
  ) THEN
    RAISE EXCEPTION 'Company with id % does not exist', v_company;
  END IF;

  -- Ensure vehicle belongs to same company
  IF NOT EXISTS (
    SELECT 1 FROM public.vehicle v
    WHERE v.id = NEW.vehicle_id
      AND v.company_id = v_company
  ) THEN
    RAISE EXCEPTION 'Vehicle % does not belong to company %', NEW.vehicle_id, v_company;
  END IF;

  NEW.company_id := v_company;
  RETURN NEW;
END;
$$;

COMMIT;

-- Optional: if you need to (re)create the trigger (only run if trigger is missing)
-- DROP TRIGGER IF EXISTS enforce_trips_integrity ON public.trips;
-- CREATE TRIGGER enforce_trips_integrity
-- BEFORE INSERT OR UPDATE ON public.trips
-- FOR EACH ROW EXECUTE FUNCTION public.enforce_trips_integrity();
