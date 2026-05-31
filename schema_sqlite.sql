CREATE TABLE IF NOT EXISTS users (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  full_name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK(role IN ('member','pengurus','admin','developer')),
  member_status TEXT NOT NULL DEFAULT 'pending' CHECK(member_status IN ('pending','active','rejected')),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS people (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  full_name TEXT NOT NULL,
  name_normalized TEXT NOT NULL DEFAULT '',
  gender TEXT NOT NULL CHECK(gender IN ('male','female')),
  marga TEXT NOT NULL,
  birth_year INTEGER,
  child_order INTEGER,
  birth_date TEXT,
  father_name TEXT NOT NULL,
  father_name_normalized TEXT,
  mother_name TEXT NOT NULL,
  mother_name_normalized TEXT,
  tarombo_status TEXT NOT NULL CHECK(tarombo_status IN ('anak','boru','bere','ibebere','spouse_of_boru','spouse_of_bere')),
  reference_female_line_name TEXT,
  spouse_name TEXT,
  spouse_marga TEXT,
  panggoaran TEXT,
  panggoaran_type TEXT CHECK(panggoaran_type IN ('permanent','temporary')),
  opung_source TEXT CHECK(opung_source IN ('son','daughter')),
  sundut INTEGER,
  sundut_locked INTEGER NOT NULL DEFAULT 0,
  submitted_by INTEGER,
  approved INTEGER NOT NULL DEFAULT 0,
  last_synced_at TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(submitted_by) REFERENCES users(id)
);

CREATE TABLE IF NOT EXISTS relationships (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  source_person_id INTEGER NOT NULL,
  target_person_id INTEGER NOT NULL,
  relation_type TEXT NOT NULL CHECK(relation_type IN ('parent','spouse')),
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(source_person_id, target_person_id, relation_type),
  FOREIGN KEY(source_person_id) REFERENCES people(id),
  FOREIGN KEY(target_person_id) REFERENCES people(id)
);

CREATE TABLE IF NOT EXISTS submitted_children (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  parent_person_id INTEGER NOT NULL,
  child_name TEXT NOT NULL,
  child_gender TEXT NOT NULL CHECK(child_gender IN ('male','female')),
  child_birth_year INTEGER,
  child_order INTEGER,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY(parent_person_id) REFERENCES people(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_user_id INTEGER,
  entity_type TEXT NOT NULL,
  entity_id INTEGER,
  action TEXT NOT NULL,
  detail_json TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS sundut_entries (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  sundut_number INTEGER NOT NULL UNIQUE,
  title TEXT NOT NULL,
  description TEXT,
  created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS form_field_configs (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  section TEXT NOT NULL,
  field_key TEXT NOT NULL UNIQUE,
  label TEXT NOT NULL,
  field_type TEXT NOT NULL DEFAULT 'text',
  placeholder TEXT,
  is_required INTEGER NOT NULL DEFAULT 1,
  is_visible INTEGER NOT NULL DEFAULT 1,
  display_order INTEGER NOT NULL DEFAULT 0,
  options_json TEXT,
  help_text TEXT,
  default_value TEXT,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS app_settings (
  key TEXT PRIMARY KEY,
  value TEXT,
  updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
);
