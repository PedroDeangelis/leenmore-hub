-- supabase-table-structure.txt
-- Supabase table structure copied/sanitized for Claude.
-- Purpose: help Claude understand current tables and design better Laravel migrations/models.

profiles
id: uuid
email: text
first_name: text
last_name: text
role: text
status: text
phone_number: text
email_receiver: bool


project
id: int8
created_at: timestamptz
title: text
results: ARRAY / json-like array
status: text
shares_issued: text
shares_target: text
start_date: timestamptz
end_date: timestamptz
message: text
link_manage_id: text


shareholder
id: int8
created_at: timestamptz
name: text
registration: text
sex: text
shares: text
shares_total: text
contact_info: text
database: text
contact_worker: text
address: text
user: ARRAY / uuid array
person_type: text
code: text
date_of_birth: date
date_of_birth_code: text
result: text
last_note: text
project_id: int8
row: int8
eletronic_voting: text
no: int8
prev_comment: text
prev_result: text
prev_note: text
api_recipient_contact: text
api_recipient_completion_date: date
contact_info_2: text


submission
id: int8
created_at: timestamptz
user_id: uuid
user_name: text
shareholder_id: int8
project_id: int8
date: timestamptz
result: text
contact_worker: text
note: text
files: ARRAY / text array
is_deleted: bool
privacy_consent_file: ARRAY / text array


receipt
id: int8
created_at: timestamptz
date: timestamptz
usage_history: text
where_used: text
amount: text
user_id: uuid
attachments: ARRAY / text array
status: bool
user_name: text
note: text


options
id: int8
created_at: timestamptz
name: text
value: text
multivalue: ARRAY / text array


resource
id: int8
created_at: timestamptz
title: text
path: text
parent_id: text
attached_to: text
order: int8
type: text
url: text