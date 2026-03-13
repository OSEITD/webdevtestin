-- handle_trip_status_change.sql
-- Trigger function: fires AFTER INSERT OR DELETE OR UPDATE on public.trips
-- Updates driver/vehicle status based on trip lifecycle.
-- IMPORTANT: column is trip_status (not status)

CREATE OR REPLACE FUNCTION public.handle_trip_status_change()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN

  -- =============================
  -- TRIP CREATED
  -- =============================
  IF TG_OP = 'INSERT' THEN
    IF NEW.driver_id IS NOT NULL THEN
      UPDATE public.drivers SET status = 'assigned' WHERE id = NEW.driver_id;
    END IF;
    IF NEW.vehicle_id IS NOT NULL THEN
      UPDATE public.vehicle SET status = 'assigned' WHERE id = NEW.vehicle_id;
    END IF;
    RETURN NEW;
  END IF;

  -- =============================
  -- TRIP UPDATED
  -- =============================
  IF TG_OP = 'UPDATE' THEN

    -- Trip accepted or started (in_transit)
    IF NEW.trip_status IN ('in_transit', 'accepted')
       AND OLD.trip_status IS DISTINCT FROM NEW.trip_status THEN
      IF NEW.driver_id IS NOT NULL THEN
        UPDATE public.drivers SET status = 'out_for_delivery' WHERE id = NEW.driver_id;
      END IF;
      IF NEW.vehicle_id IS NOT NULL THEN
        UPDATE public.vehicle SET status = 'out_for_delivery' WHERE id = NEW.vehicle_id;
      END IF;
    END IF;

    -- Trip completed or cancelled
    IF NEW.trip_status IN ('completed', 'cancelled')
       AND OLD.trip_status IS DISTINCT FROM NEW.trip_status THEN
      IF NEW.driver_id IS NOT NULL THEN
        UPDATE public.drivers SET status = 'available' WHERE id = NEW.driver_id;
      END IF;
      IF NEW.vehicle_id IS NOT NULL THEN
        UPDATE public.vehicle SET status = 'available' WHERE id = NEW.vehicle_id;
      END IF;
    END IF;

    RETURN NEW;
  END IF;


  IF TG_OP = 'DELETE' THEN
    IF OLD.driver_id IS NOT NULL THEN
      UPDATE public.drivers SET status = 'available' WHERE id = OLD.driver_id;
    END IF;
    IF OLD.vehicle_id IS NOT NULL THEN
      UPDATE public.vehicle SET status = 'available' WHERE id = OLD.vehicle_id;
    END IF;
    RETURN OLD;
  END IF;

  RETURN NULL;
END;
$$;
