-- Tarombo PTSBI — PostgreSQL schema (SQLite fallback via USE_SQLITE=1)

CREATE TABLE IF NOT EXISTS users (
  id SERIAL PRIMARY KEY,
  full_name TEXT NOT NULL,
  email TEXT NOT NULL UNIQUE,
  password_hash TEXT NOT NULL,
  role TEXT NOT NULL CHECK (role IN ('member', 'pengurus', 'admin', 'developer')),
  member_status TEXT NOT NULL DEFAULT 'pending'
    CHECK (member_status IN ('pending', 'active', 'rejected')),
  phone TEXT NOT NULL DEFAULT '',
  wp_user_id INTEGER UNIQUE,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS member_profiles (
  id SERIAL PRIMARY KEY,
  user_id INTEGER NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
  wp_user_id INTEGER UNIQUE,
  family_no TEXT NOT NULL DEFAULT '',
  kepala_keluarga TEXT NOT NULL DEFAULT '',
  nama_istri TEXT NOT NULL DEFAULT '',
  tarombo TEXT NOT NULL DEFAULT '',
  oppu TEXT NOT NULL DEFAULT '',
  nomor_sundut TEXT NOT NULL DEFAULT '',
  hula_boru TEXT NOT NULL DEFAULT '',
  phone TEXT NOT NULL DEFAULT '',
  country_name TEXT NOT NULL DEFAULT 'Indonesia',
  country_code TEXT NOT NULL DEFAULT 'ID',
  province TEXT NOT NULL DEFAULT '',
  city TEXT NOT NULL DEFAULT '',
  district TEXT NOT NULL DEFAULT '',
  subdistrict TEXT NOT NULL DEFAULT '',
  postal_code TEXT NOT NULL DEFAULT '',
  state_city TEXT NOT NULL DEFAULT '',
  address_detail TEXT NOT NULL DEFAULT '',
  street_name TEXT NOT NULL DEFAULT '',
  house_number TEXT NOT NULL DEFAULT '',
  rt TEXT NOT NULL DEFAULT '',
  rw TEXT NOT NULL DEFAULT '',
  is_overseas BOOLEAN NOT NULL DEFAULT FALSE,
  profile_complete BOOLEAN NOT NULL DEFAULT FALSE,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_member_profiles_wp ON member_profiles (wp_user_id);

CREATE TABLE IF NOT EXISTS people (
  id SERIAL PRIMARY KEY,
  full_name TEXT NOT NULL,
  name_normalized TEXT NOT NULL DEFAULT '',
  gender TEXT NOT NULL CHECK (gender IN ('male', 'female')),
  marga TEXT NOT NULL,
  birth_year INTEGER,
  child_order INTEGER,
  birth_date DATE,
  father_name TEXT NOT NULL,
  father_name_normalized TEXT,
  mother_name TEXT NOT NULL,
  mother_name_normalized TEXT,
  tarombo_status TEXT NOT NULL CHECK (
    tarombo_status IN ('anak', 'boru', 'bere', 'ibebere', 'spouse_of_boru', 'spouse_of_bere')
  ),
  reference_female_line_name TEXT,
  spouse_name TEXT,
  spouse_marga TEXT,
  panggoaran TEXT,
  panggoaran_type TEXT CHECK (panggoaran_type IN ('permanent', 'temporary')),
  opung_source TEXT CHECK (opung_source IN ('son', 'daughter')),
  sundut INTEGER,
  sundut_locked BOOLEAN NOT NULL DEFAULT FALSE,
  submitted_by INTEGER REFERENCES users(id),
  approved BOOLEAN NOT NULL DEFAULT FALSE,
  last_synced_at TIMESTAMPTZ,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_people_match_loose ON people (name_normalized, marga);
CREATE INDEX IF NOT EXISTS idx_people_match_strict ON people (name_normalized, marga, father_name_normalized, birth_year);
CREATE INDEX IF NOT EXISTS idx_people_approved ON people (approved);

CREATE TABLE IF NOT EXISTS relationships (
  id SERIAL PRIMARY KEY,
  source_person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
  target_person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
  relation_type TEXT NOT NULL CHECK (relation_type IN ('parent', 'spouse')),
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
  UNIQUE (source_person_id, target_person_id, relation_type)
);

CREATE TABLE IF NOT EXISTS submitted_children (
  id SERIAL PRIMARY KEY,
  parent_person_id INTEGER NOT NULL REFERENCES people(id) ON DELETE CASCADE,
  child_name TEXT NOT NULL,
  child_gender TEXT NOT NULL CHECK (child_gender IN ('male', 'female')),
  child_birth_year INTEGER,
  child_order INTEGER,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS audit_logs (
  id SERIAL PRIMARY KEY,
  actor_user_id INTEGER REFERENCES users(id),
  entity_type TEXT NOT NULL,
  entity_id INTEGER,
  action TEXT NOT NULL,
  detail_json TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS sundut_entries (
  id SERIAL PRIMARY KEY,
  sundut_number INTEGER NOT NULL UNIQUE,
  title TEXT NOT NULL,
  description TEXT,
  created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS form_field_configs (
  id SERIAL PRIMARY KEY,
  section TEXT NOT NULL,
  field_key TEXT NOT NULL UNIQUE,
  label TEXT NOT NULL,
  field_type TEXT NOT NULL DEFAULT 'text',
  placeholder TEXT,
  is_required BOOLEAN NOT NULL DEFAULT TRUE,
  is_visible BOOLEAN NOT NULL DEFAULT TRUE,
  display_order INTEGER NOT NULL DEFAULT 0,
  options_json TEXT,
  help_text TEXT,
  default_value TEXT,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_form_fields_section ON form_field_configs (section, display_order);

CREATE TABLE IF NOT EXISTS app_settings (
  key TEXT PRIMARY KEY,
  value TEXT,
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS member_profiles (
  id SERIAL PRIMARY KEY,
  wp_user_id INTEGER,
  email TEXT NOT NULL UNIQUE,
  profile_json TEXT NOT NULL DEFAULT '{}',
  updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_member_profiles_wp_user ON member_profiles (wp_user_id);
