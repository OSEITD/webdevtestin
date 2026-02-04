-- trigger_set_available.sql
-- Purpose: When a trip is completed or deleted, set the assigned driver and vehicle status back to 'available'.
-- Instructions: Adjust table/column names and status string values to match your schema before applying.

-- Assumptions (edit if your schema differs):
--  - trips table: public.trips with columns: id (PK), driver_id, vehicle_id, status
--  - drivers table: public.drivers with columns: id (PK), status
--  - vehicles table: public.vehicles with columns: id (PK), status
--  - status values: use 'available' to mark free resources. Replace with your project's status value if different.

CREATE OR REPLACE FUNCTION public.trips_set_driver_vehicle_available()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
  -- Handle DELETE: use OLD row
  IF TG_OP = 'DELETE' THEN
    IF OLD.driver_id IS NOT NULL THEN
      UPDATE public.drivers
      SET status = 'available'
      WHERE id = OLD.driver_id
        AND (status IS DISTINCT FROM 'available' OR status IS NULL);
    END IF;

    IF OLD.vehicle_id IS NOT NULL THEN
      UPDATE public.vehicles
      SET status = 'available'
      WHERE id = OLD.vehicle_id
        AND (status IS DISTINCT FROM 'available' OR status IS NULL);
    END IF;

    RETURN OLD;
  END IF;

  -- Handle UPDATE: when trip status becomes 'complete' (or 'completed')
  IF TG_OP = 'UPDATE' THEN
    -- Only act when status changed to a completion state
    IF (NEW.status = 'complete' OR NEW.status = 'completed')
       AND (OLD.status IS DISTINCT FROM NEW.status) THEN

      IF NEW.driver_id IS NOT NULL THEN
        UPDATE public.drivers
        SET status = 'available'
        WHERE id = NEW.driver_id
          AND (status IS DISTINCT FROM 'available' OR status IS NULL);
      END IF;

      IF NEW.vehicle_id IS NOT NULL THEN
        UPDATE public.vehicles
        SET status = 'available'
        WHERE id = NEW.vehicle_id
          AND (status IS DISTINCT FROM 'available' OR status IS NULL);
      END IF;
    END IF;

    RETURN NEW;
  END IF;

  RETURN NULL; -- should not reach here
END;
$$;

-- Create trigger (AFTER so the trip row change is committed and visible)
DROP TRIGGER IF EXISTS trips_set_available_trigger ON public.trips;
CREATE TRIGGER trips_set_available_trigger
AFTER UPDATE OR DELETE ON public.trips
FOR EACH ROW
EXECUTE FUNCTION public.trips_set_driver_vehicle_available();

-- Optional: If you also want to set available when a trip is inserted with status 'cancelled' or similar,
-- add INSERT to the trigger and adjust logic accordingly.

-- ======= Test queries =======
-- 1) Test UPDATE path: set a trip's status to 'complete'
-- UPDATE public.trips SET status = 'complete' WHERE id = '<some-trip-id>';

-- 2) Test DELETE path: delete a trip row (ensure you have a backup or test DB)
-- DELETE FROM public.trips WHERE id = '<some-trip-id>';

-- 3) Verify driver/vehicle status update
-- SELECT id, status FROM public.drivers WHERE id = '<driver-id>';
-- SELECT id, status FROM public.vehicles WHERE id = '<vehicle-id>';

-- ======= Notes & customization =======
-- - If your primary key fields are named differently (e.g. driver_uuid), replace `id` accordingly.
-- - If statuses use integer codes (0/1), change `SET status = 'available'` to the appropriate value.
-- - If drivers/vehicles are in a different schema, adjust the schema prefix from `public.` to your schema.
-- - If you prefer the trigger to run BEFORE instead of AFTER, consider concurrency implications; AFTER is safer for relying on committed FK values.
-- - For Supabase: you can paste this SQL into the SQL editor (Dashboard -> SQL) and run it.
-- - To deploy via psql or supabase CLI, use the usual connection string and `psql -f trigger_set_available.sql` or `supabase db remote commit` workflows.
DECLARE
  v_company uuid;
BEGIN
  -- Priority: use provided company_id first
  IF NEW.company_id IS NOT NULL THEN
    v_company := NEW.company_id;

  -- Fallback: resolve from outlet_manager_id if provided
  ELSIF NEW.outlet_manager_id IS NOT NULL THEN
    SELECT company_id INTO v_company
    FROM public.outlets
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