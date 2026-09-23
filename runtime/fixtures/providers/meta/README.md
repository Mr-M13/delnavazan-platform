# Meta (WhatsApp) provider fixture contract (future phase)

The current Platform has **no WhatsApp/Meta adapter** and performs no external
send. Notifications, when they are later authorised, must route through an
explicit delivery seam and never through a live provider in the disposable
runtime.

When a Meta adapter is authorised it must:

- accept a provider-neutral notification intent and map it to a provider
  template outside canonical business storage;
- store only a digest reference to the outbound message/recipient (no raw phone
  number, no template content as authority);
- be exercised with [sample-message.json](sample-message.json) through a mock
  transport, with no real phone number or API credential.

The sample recipient is synthetic and the payload is illustrative only.
