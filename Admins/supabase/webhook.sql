-- Create a function that will be triggered by the webhook
create or replace function new_notification_webhook()
returns trigger as $$
begin
  -- Make a POST request to the new-notification function
  perform net.http_post(
    url:='https://xerpchdsykqafrsxbqef.supabase.co/functions/v1/new-notification',
    body:=json_build_object('record', new)
  );
  return new;
end;
$$ language plpgsql;

-- Create a trigger that will call the function whenever a new row is inserted into the notifications table
create trigger new_notification_trigger
after insert on notifications
for each row execute procedure new_notification_webhook();
