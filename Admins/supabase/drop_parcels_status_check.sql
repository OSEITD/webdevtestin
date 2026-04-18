-- Drop the parcel status check constraint so the app can keep the original status workflow.
-- Run this once in Supabase SQL editor.

ALTER TABLE public.parcels
DROP CONSTRAINT IF EXISTS parcels_status_check;
