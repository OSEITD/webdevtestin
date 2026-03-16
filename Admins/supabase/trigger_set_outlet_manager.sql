-- trigger_set_outlet_manager.sql
-- Purpose: Automatically populate `outlets.manager_id` when a new outlet is inserted.
-- Adjust the logic below to match your schema. Run in Supabase SQL editor or via psql.

-- Notes / Assumptions:
-- - `outlets.manager_id` references `profiles.id` (UUID).
-- - In Supabase, `auth.uid()` returns the current authenticated user's UUID (when request originates from a logged-in session).
-- - If inserts are made using the service_role key (server-side migration), `auth.uid()` will be NULL.
-- - If your `profiles` table stores the auth id in a different column (e.g. `user_id`), see alternative SQL below.

CREATE OR REPLACE FUNCTION public.set_outlet_manager_on_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    cur_uid uuid;
    mgr uuid;
BEGIN
    -- Only set when manager_id not provided by the inserter
    IF NEW.manager_id IS NULL THEN
        -- Try the simple approach: set manager_id to the authenticated user's id
        BEGIN
            cur_uid := auth.uid();
        EXCEPTION WHEN others THEN
            cur_uid := NULL;
        END;

        IF cur_uid IS NOT NULL THEN
            -- If your profiles.id equals auth.uid(), assign directly
            NEW.manager_id := cur_uid;
        ELSE
            -- Fallback: attempt to lookup a reasonable manager via profiles table
            -- Example: if profiles.user_id stores the auth UID, uncomment and adjust the following
            -- SELECT id INTO mgr FROM public.profiles WHERE user_id = auth.uid() LIMIT 1;
            -- IF mgr IS NOT NULL THEN
            --     NEW.manager_id := mgr;
            -- END IF;

            -- Optionally: set a default company manager if you have a companies table
            -- SELECT default_manager_id INTO mgr FROM public.companies WHERE id = NEW.company_id LIMIT 1;
            -- IF mgr IS NOT NULL THEN
            --     NEW.manager_id := mgr;
            -- END IF;

            -- If none of the above apply, NEW.manager_id remains NULL (you may want to enforce NOT NULL via application)
        END IF;
    END IF;

    RETURN NEW;
END;
$$;

DROP TRIGGER IF EXISTS set_outlet_manager_trigger ON public.outlets;
CREATE TRIGGER set_outlet_manager_trigger
BEFORE INSERT ON public.outlets
FOR EACH ROW
EXECUTE FUNCTION public.set_outlet_manager_on_insert();

-- ====== Test commands ======
-- 1) Insert new outlet (when logged-in as a user):
-- INSERT INTO public.outlets (id, company_id, name) VALUES ('a-uuid', 'company-uuid', 'My Outlet');
-- After this insert, if you performed it while authenticated, `manager_id` should be set to your auth UID.

-- 2) Insert with explicit manager_id (preserves provided value):
-- INSERT INTO public.outlets (id, company_id, name, manager_id) VALUES ('b-uuid', 'company-uuid', 'Other Outlet', 'profile-uuid');

-- 3) If you need to set manager_id using profiles.user_id mapping, edit the function above and uncomment the SELECT that maps `profiles.user_id` => `profiles.id`.

-- Apply: paste this into Supabase SQL editor and run. For psql use: psql "<CONN_STRING>" -f trigger_set_outlet_manager.sql
