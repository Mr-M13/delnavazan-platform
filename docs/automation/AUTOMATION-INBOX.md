# Delnavazan Automation Inbox

This file exists only to give the local Delnavazan automation a durable GitHub event surface that ChatGPT Work can monitor.

## Purpose

The local planner/orchestrator may post structured pull-request comments when it reaches a state that requires Central Director intervention or Hamed's input.

Supported event classes:

```
AUTOMATION EVENT
project=delnavazan
event=technical_escalation|human_action_required|recovery_resolved
task_id=<task-id>
severity=blocking|warning|info
owner=cd|hamed
human_action_required=true|false
reason=<short reason>
evidence=<short evidence>
```

## Rules

- This PR is an automation inbox and is not intended to be merged.
- Routine informational events should not be posted here.
- Technical escalations should be posted only after safe local retries/recovery are exhausted.
- Human-action events must explain exactly what Hamed is being asked to do and what will happen after approval.
- No secrets, credentials, personal data, or production tokens may be included in comments.
