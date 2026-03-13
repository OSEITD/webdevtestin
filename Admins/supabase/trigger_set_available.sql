-- trigger_set_available.sql
-- Purpose: When a trip is completed or deleted, set the assigned driver and vehicle status back to 'available'.
-- IMPORTANT: Column is trip_status (not status), table is vehicle (not vehicles).

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
      UPDATE public.vehicle
      SET status = 'available'
      WHERE id = OLD.vehicle_id
        AND (status IS DISTINCT FROM 'available' OR status IS NULL);
    END IF;

    RETURN OLD;
  END IF;

  -- Handle UPDATE: when trip_status becomes 'completed'
  IF TG_OP = 'UPDATE' THEN
    IF NEW.trip_status = 'completed'
       AND (OLD.trip_status IS DISTINCT FROM NEW.trip_status) THEN

      IF NEW.driver_id IS NOT NULL THEN
        UPDATE public.drivers
        SET status = 'available'
        WHERE id = NEW.driver_id
          AND (status IS DISTINCT FROM 'available' OR status IS NULL);
      END IF;

      IF NEW.vehicle_id IS NOT NULL THEN
        UPDATE public.vehicle
        SET status = 'available'
        WHERE id = NEW.vehicle_id
          AND (status IS DISTINCT FROM 'available' OR status IS NULL);
      END IF;
    END IF;

    RETURN NEW;
  END IF;

  RETURN NULL;
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
-- 1) Test UPDATE path: set a trip's trip_status to 'completed'
-- UPDATE public.trips SET trip_status = 'completed' WHERE id = '<some-trip-id>';

-- 2) Test DELETE path: delete a trip row (ensure you have a backup or test DB)
-- DELETE FROM public.trips WHERE id = '<some-trip-id>';

-- 3) Verify driver/vehicle status update
-- SELECT id, status FROM public.drivers WHERE id = '<driver-id>';
-- SELECT id, status FROM public.vehicle WHERE id = '<vehicle-id>';