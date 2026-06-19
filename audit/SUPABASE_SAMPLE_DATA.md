-- sample-data-sanitized.txt
-- Fake sample data only.
-- Purpose: help Claude understand table shape, relationships, JSON/array fields, and meaning.
-- Do not include real names, emails, phone numbers, shareholder data, notes, or file paths.

-- profiles
id: "user-uuid-admin-1"
email: "admin@example.com"
first_name: "Admin"
last_name: "User"
role: "admin"
status: "active"
phone_number: "010-0000-0000"
email_receiver: true

-- profiles
id: "user-uuid-worker-1"
email: "worker@example.com"
first_name: "Worker"
last_name: "User"
role: "worker"
status: "active"
phone_number: "010-1111-1111"
email_receiver: true


-- project
id: 1
title: "Example Project"
status: "publish"
shares_issued: "1000000"
shares_target: "500000"
start_date: "2025-01-01"
end_date: "2025-02-01"
message: "Example message shown to workers."
link_manage_id: "example-project-link"
results: [
  {
    "name": "Completed",
    "color": "#22c55e",
    "contactRequired": false,
    "attachmentRequired": false,
    "order": 0
  },
  {
    "name": "No Answer",
    "color": "#f97316",
    "contactRequired": true,
    "attachmentRequired": false,
    "order": 1
  },
  {
    "name": "Rejected",
    "color": "#ef4444",
    "contactRequired": true,
    "attachmentRequired": true,
    "order": 2
  }
]


-- shareholder
id: 1
project_id: 1
name: "Example Shareholder One"
registration: "REG-001"
sex: "M"
shares: "100"
shares_total: "100"
contact_info: "010-2222-2222"
contact_info_2: "010-3333-3333"
database: "example-database"
contact_worker: "Worker User"
address: "Example Address 1"
user: ["user-uuid-worker-1"]
person_type: "individual"
code: "CODE001"
date_of_birth: "1990-01-01"
date_of_birth_code: "900101"
result: "No Answer"
last_note: "Example previous note"
row: 1
eletronic_voting: "no"
no: 1
prev_comment: "Example previous comment"
prev_result: "Previous Result"
prev_note: "Previous note"
api_recipient_contact: "010-4444-4444"
api_recipient_completion_date: "2025-01-03"

-- shareholder
id: 2
project_id: 1
name: "Example Shareholder Two"
registration: "REG-002"
sex: "F"
shares: "250"
shares_total: "250"
contact_info: "010-5555-5555"
contact_info_2: "010-6666-6666"
database: "example-database"
contact_worker: "Worker User"
address: "Example Address 2"
user: ["user-uuid-worker-1"]
person_type: "corporation"
code: "CODE002"
date_of_birth: "1985-05-05"
date_of_birth_code: "850505"
result: "Completed"
last_note: "Example completed note"
row: 2
eletronic_voting: "yes"
no: 2
prev_comment: "Example previous comment"
prev_result: "Previous Result"
prev_note: "Previous note"
api_recipient_contact: "010-7777-7777"
api_recipient_completion_date: "2025-01-04"


-- submission
id: 1
user_id: "user-uuid-worker-1"
user_name: "Worker User"
shareholder_id: 1
project_id: 1
date: "2025-01-05"
result: "No Answer"
contact_worker: "Worker User"
note: "Example submission note."
files: [
  "upload/example-project/2025-01-05/example-file.pdf"
]
privacy_consent_file: [
  "privacy-consent/example-project/example-consent.pdf"
]
is_deleted: false

-- submission
id: 2
user_id: "user-uuid-worker-1"
user_name: "Worker User"
shareholder_id: 2
project_id: 1
date: "2025-01-06"
result: "Completed"
contact_worker: "Worker User"
note: "Example completed submission note."
files: [
  "upload/example-project/2025-01-06/example-image.jpg"
]
privacy_consent_file: [
  "privacy-consent/example-project/example-consent-2.pdf"
]
is_deleted: false


-- receipt
id: 1
date: "2025-01-07"
usage_history: "Transport"
where_used: "Example Location"
amount: "25000"
user_id: "user-uuid-worker-1"
user_name: "Worker User"
attachments: [
  "receipts/worker-user/2025-01-07/example-receipt.jpg"
]
status: true
note: "Example receipt note."

-- receipt
id: 2
date: "2025-01-08"
usage_history: "Meal"
where_used: "Example Restaurant"
amount: "12000"
user_id: "user-uuid-worker-1"
user_name: "Worker User"
attachments: [
  "receipts/worker-user/2025-01-08/example-receipt-2.jpg"
]
status: true
note: "Example meal receipt."


-- options
id: 1
name: "submission_deadline"
value: "2025-02-01"
multivalue: null

-- options
id: 2
name: "usage_history"
value: null
multivalue: [
  "Transport",
  "Meal",
  "Accommodation",
  "Office Supplies",
  "Other"
]


-- resource
id: 1
title: "Example Resource File"
path: "resources/example-project/example-document.pdf"
parent_id: "1"
attached_to: "project"
order: 1
type: "file"
url: null

-- resource
id: 2
title: "Example Resource Link"
path: null
parent_id: "1"
attached_to: "project"
order: 2
type: "link"
url: "https://example.com/resource"