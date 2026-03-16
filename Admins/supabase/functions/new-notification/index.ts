import { serve } from 'https://deno.land/std@0.168.0/http/server.ts'
import { createClient } from 'https://esm.sh/@supabase/supabase-js@2'

serve(async (req) => {
  const { record } = await req.json()

  // This is the new notification that was inserted
  const newNotification = record

  // You can now send this notification to the client using your preferred method.
  // For example, you could use a WebSocket server or a push notification service.

  console.log('New notification:', newNotification)

  return new Response('OK')
})
