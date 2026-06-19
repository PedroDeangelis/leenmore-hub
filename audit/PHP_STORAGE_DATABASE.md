-- php-storage-table-structure.txt
-- Source: u333349367_storage.sql
-- Purpose: help Claude understand the old PHP storage database before migrating it into Laravel.
-- This is schema structure only, not real data.

email_attachments
id: int(11), primary key, auto increment
job_id: int(11), foreign key -> email_jobs.id, on delete cascade
resource_id: int(11)
filename: varchar(255)
file_path: varchar(500)
file_size: int(11), nullable

indexes:
primary key: id
index: job_id


email_jobs
id: int(11), primary key, auto increment
project_id: int(11), indexed
subject: varchar(255)
body: text
worker_report: text, nullable
worker_report_pdf: text, nullable
created_by_admin_id: int(11)
status: enum('pending','processing','completed','failed'), default 'pending'
created_at: datetime, default current_timestamp()
updated_at: datetime, default current_timestamp(), updates automatically

indexes:
primary key: id
index: project_id
index: status


email_links
id: int(11), primary key, auto increment
job_id: int(11), foreign key -> email_jobs.id, on delete cascade
title: varchar(255)
url: varchar(1000)
sort_order: int(11), default 0

indexes:
primary key: id
index: job_id


email_recipients
id: int(11), primary key, auto increment
job_id: int(11), foreign key -> email_jobs.id, on delete cascade
worker_id: int(11)
email: varchar(255)
status: enum('pending','processing','sent','failed'), default 'pending'
error_message: text, nullable
sent_at: datetime, nullable
attempts: int(11), default 0
last_attempt_at: datetime, nullable

indexes:
primary key: id
index: job_id
index: status


esignon_auth
id: int(11), primary key
access_token: text
expire_date: datetime
updated_at: datetime, default current_timestamp(), updates automatically

indexes:
primary key: id


esignon_shareholders
id: int(11), primary key, auto increment
project_id: varchar(36)
link_manage_id: int(11)
link_id: int(11)
identifier: text
name: varchar(255), nullable
contact: varchar(255), nullable
completed_date: datetime, nullable
data_hash: char(64)
updated_at: datetime, default current_timestamp(), updates automatically
supabase_synced_at: datetime, nullable
needs_supabase_sync: tinyint(1), default 1

indexes:
primary key: id
unique index: project_id + link_id
index: needs_supabase_sync


project
id: int(11), primary key
title: text
created_at: date

indexes:
primary key: id


supabase_projects_cache
id: bigint(20), primary key
status: varchar(20)
link_manage_id: int(11), nullable
last_seen_at: datetime, default current_timestamp()
updated_at: datetime, default current_timestamp(), updates automatically

indexes:
primary key: id
index: status
index: link_manage_id