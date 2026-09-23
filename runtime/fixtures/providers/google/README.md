# Google provider fixture contract (future phase)

The current Platform has **no Google Calendar/Meet adapter**. Canonical Lesson
scheduling is owned by `CanonicalLessonScheduleService` and stores only
schedule facts (timezone, wall-clock date/time, UTC start/end). There is no
calendar or Meet authority and no external call.

When a Google adapter is authorised it must:

- keep the canonical schedule as the source of truth and treat any provider
  calendar event as a mapping, never as schedule authority;
- persist only a digest reference to a provider event (no raw token, no
  refresh token, no Meet URL as business identity);
- be exercised through a mock transport using [sample-calendar-event.json](sample-calendar-event.json),
  with no real API or credential use.

The sample is synthetic and is not associated with any real calendar.
