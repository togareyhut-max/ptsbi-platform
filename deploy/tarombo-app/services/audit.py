"""Audit log for admin actions."""

from __future__ import annotations

import json

from db import coerce_submitted_by


def log_audit(db, actor_user_id, entity_type: str, entity_id, action: str, detail=None):
    db.execute(
        """
        INSERT INTO audit_logs (actor_user_id, entity_type, entity_id, action, detail_json)
        VALUES (?, ?, ?, ?, ?)
        """,
        (
            coerce_submitted_by(db, actor_user_id),
            entity_type,
            entity_id,
            action,
            json.dumps(detail, ensure_ascii=False) if detail is not None else None,
        ),
    )
