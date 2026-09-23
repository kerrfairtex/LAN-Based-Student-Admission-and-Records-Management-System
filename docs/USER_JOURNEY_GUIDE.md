# TRAC JHS SARMS User Journey Guide

A step-by-step operational manual for the people who use the TRAC JHS Student
Admission and Records Management System every day.

Evidence baseline: repository revision fe4b778, verified in a live browser
against the running application with seeded records, registrar and encoder
accounts, mobile emulation, and error-path testing. Where a workflow could not
be demonstrated in the running application, this guide says so instead of
guessing.

This guide covers the journey of using the system. For the complete technical
and operational reference, including installation and deployment, see
User Guidelines on the public site or docs/USER_GUIDE.md in the repository.

## 1. About This Guide

### 1.1 Who Should Use This Guide

This guide is written for the School Registrar, Data Encoders, and anyone the
registrar assigns administrator duties to. It assumes you can use a browser and
a keyboard and nothing else. If you have never used the system before, read
sections 2 through 9 first, then follow the journey sections for the work you
need to do.

### 1.2 How Status Labels Are Used

Every workflow in this guide carries a status label so you know what to expect
before you start.

| Label | Meaning |
|---|---|
| VERIFIED WORKING | Demonstrated working in the live application |
| IMPLEMENTED / CODE-VERIFIED | Exists in the system but was not fully demonstrated at this revision |
| KNOWN BROKEN | Currently fails every time; requires a software fix |
| DEGRADED | Works partially or has a known reliability problem |
| NOT VERIFIED | Not enough evidence to say either way |

When a workflow is marked KNOWN BROKEN, this guide tells you exactly what
happens when you try it, and what to do instead. Do not retry a broken action
repeatedly; the result will not change until the software is fixed.

### 1.3 Where the System Runs

The system is served from the school network. Staff open it in a browser at the
address given by the school (for example, the deployed site address or the
server address on the local network). Any modern browser works. Nothing is
installed on your computer or phone.

## 2. System Overview

TRAC JHS SARMS is the school's internal system for admission applications,
student records, enrollment, grades on School Form 10, transfers, reports, and
DepEd LIS CSV exports. It has two sides:

1. A public website that anyone can see: the landing page, About, Privacy,
   Terms, Contact, and the User Guidelines page.
2. The staff system, which requires a username and password. This is where all
   the work happens.

The public website is informational. It is not an online admission portal.
Admission applications are entered into the staff system by school staff after
a parent or guardian submits an inquiry or arrives at the registrar's office.

The core of the staff system is one continuous flow:

1. An admission application is recorded.
2. The registrar reviews it and approves it.
3. Approval creates the student record and an enrollment for the school year.
4. Grades are entered on the SF10 for that student.
5. Reports are generated from the recorded data.

Each stage feeds the next. Section 26 walks through this flow end to end.

## 3. Who Uses the System

There are two staff roles.

The School Registrar has full access: admissions, students, enrollment,
records, reports, transfers, search, LIS, user management, settings, the audit
log, backup, and restore. The registrar is the only role that can create
users, manage school years and sections, and use backup and restore.

The Data Encoder has data-entry access: admissions, students, enrollment,
records, reports, transfers, and search. The Administration section of the
sidebar is not shown to encoders, and if an encoder opens an administration
page address directly, the system stops them with the message: Only the School
Registrar can perform this action.

Both roles see the same dashboard, admissions, and records screens. The
difference is the Administration area and the quick-action tiles on the
dashboard: the registrar sees six tiles (New Admission, Transfer, Search,
Reports, Backup, LIS CSV), the encoder sees four (New Admission, Transfer,
Search, Reports).

## 4. Before You Start

You need three things:

1. A username and password given to you by the registrar.
2. The system address in your browser.
3. The name of your role, so you know which parts of this guide apply to you.

Two starter accounts exist on fresh installations, one registrar and one
encoder. Their passwords are published in the repository, so the registrar
must change both passwords on first sign-in, and every user should change
their own password from the sidebar before doing real work. Section 25
explains how.

If you forget your password, use the Forgot password link on the sign-in page,
which opens an email to the registrar's office, or visit the registrar in
person. Passwords cannot be reset from the sign-in page itself.

## 5. Opening the Website

Type the system address into your browser and press Enter. The public landing
page loads. You are not signed in yet; everything you see here is public.

## 6. Understanding the Public Landing Page

The landing page has a dark green background with gold accents. Across the
top, on the left, is the school seal and the name TRAC JHS with Bongao,
Tawi-Tawi underneath. The navigation bar contains these items:

| Navigation item | Where it goes |
|---|---|
| About | Scrolls to the About the school section of the landing page |
| Academics | Scrolls to the Academics section describing the programs |
| Admissions | Scrolls to the Admissions section explaining how to apply |
| Campus | Scrolls to the Campus section |
| Contact | Scrolls to the contact and inquiry area at the bottom |
| Staff Sign In | Goes to the staff sign-in page |

Below the navigation, the page presents the school, its programs, admission
information, and a frequently asked questions area. At the bottom is a contact
block with the registrar's office hours, location, and email address, next to
an inquiry form. In the footer there are links to Privacy, Terms, About, and
User Guidelines. The User Guidelines link opens the full system guide on its
own page.

On a phone, the navigation collapses into a menu button. Tap it to open the
list of sections, and tap any section or the Staff Sign In button inside that
menu.

About the inquiry form: it accepts a name, grade level, and contact number.
Its current status is KNOWN BROKEN. When a visitor fills it in and presses the
send button, the button shows a sending state and then returns to normal, and
nothing is saved. No success or error message appears. Until this is fixed,
treat the form as decorative: accept inquiries in person, by phone, or by
email to the registrar's office instead. Do not tell parents to use the online
form.

## 7. Staff Sign In

Status: VERIFIED WORKING

1. Open the system address in your browser.
2. Select Staff Sign In at the top right of the landing page. The sign-in page
   opens with two fields.
3. Enter your username in the Username field. The cursor is already placed
   there for you.
4. Enter your password in the Password field.
5. Select Sign In.

If the username and password are correct, you are taken to the Dashboard.

If they are wrong, the page returns with the message: Invalid credentials or
inactive account. Check your typing, confirm with the registrar that your
account is active, and try again. If you have failed several times in a row,
the system deliberately slows down sign-in attempts for your username and
address as a protection against guessing. Wait a few minutes or ask the
registrar to clear the failed-attempt counter from the Users page.

If you have forgotten your password, select the Forgot password link below
the sign-in button and send the email it prepares, or see the registrar.

## 8. Understanding the Dashboard

Status: VERIFIED WORKING

After signing in you land on the Dashboard. It has four areas.

The sidebar on the left lists where you can go. For the registrar it shows
Dashboard, then an Admissions group with Admissions, Students, Enrollments,
and Records, then a Reports group with Reports, Transfers, and Search, then an
Administration group with Users, Settings, Audit, LIS, Backup, and Restore.
For the encoder, the Administration group is not shown. At the bottom of the
sidebar is your name and role, a Change Password button, and a Sign Out
button.

The top bar shows the page title on the left. On the right are the active
school year selector, the notification bell, and your initials. The school
year selector is a menu; the registrar can open it and select a different
school year to make it active. Note that after switching the year you land on
the Dashboard rather than the page you were on. The bell shows a red count of
overdue transfers; open it to see the overdue list and recent activity, each
entry linking to the relevant record.

The statistics row shows five cards. Active Students counts students whose
status is active. Pending Admissions counts applications awaiting review.
Enrolled shows enrollment for the active school year. Unassigned Sections
counts enrolled students who do not yet have a section. Overdue Transfers
counts open transfers past their 30-day deadline. The numbers count up from
zero when the page loads; the final value is the true count, so if a number
looks wrong, wait a moment for it to settle.

The quick actions row shows tiles that jump straight to common tasks: New
Admission, Transfer, Search, Reports, and for the registrar also Backup and
LIS CSV. Below them, Recent Activity lists the latest actions recorded in the
audit log, with who did them and when.

## 9. Understanding the Sidebar and Navigation

Select any item in the sidebar to open that page. The current page is
highlighted. On a phone, the sidebar is hidden behind the menu button at the
top left; tap it to slide the sidebar in, and tap the dark area or press
Escape to close it. The sidebar works the same on every staff page.

## 10. Registrar Journey Overview

The registrar's day typically moves between these screens:

1. Check the Dashboard for pending admissions and overdue transfers.
2. Open Admissions to record new applications brought in by parents.
3. Review each pending application and either approve it, which creates the
   student and enrollment, or reject it.
4. Open Enrollments to see who lacks a section.
5. Open Records to enter academic records and SF10 grades.
6. Open Reports to produce what the office needs.
7. Handle transfers as they come in.
8. Periodically create a backup, and manage school years, sections, and user
   accounts as needed.

Each of these is covered in its own section below.

## 11. Encoder Journey Overview

The encoder's work is data entry:

1. Sign in and check the Dashboard.
2. Record admission applications under Admissions.
3. Look up students under Students or Search.
4. Enter grades under Records.
5. Generate reports under Reports.

The encoder does not see the Administration group. If an encoder is given an
administration page address and opens it, the system shows: Only the School
Registrar can perform this action. This is normal and expected.

## 12. Admissions Journey

### 12.1 Opening the Admissions List

Status: VERIFIED WORKING

From the Dashboard, select Admissions in the sidebar. The Admissions page
shows every application in a table with columns for the applicant, the
application number, school year, grade level, type, status, date, and actions.
Above the table are filters: a search box, a status filter, a school year
filter, a grade level filter, and a type filter. Set any combination and the
table narrows; select Clear filters to reset. The table shows twenty rows at a
time with page buttons below when there are more.

If the table shows No admission applications yet, the system has none
recorded. If it shows No admission applications match the current filters,
records exist but your filters exclude them; clear the filters.

### 12.2 Creating a New Admission

Status: VERIFIED WORKING

1. From the Admissions page, select New Application. You can also use the New
   Admission tile on the Dashboard. The New Admission form opens.
2. Select the School Year. It defaults to the active year.
3. Select the Grade Level the applicant is entering.
4. Select the Enrollment Type: New, Returning, or Transferee.
5. Enter the applicant's First Name and Last Name. Middle Name and Suffix are
   optional.
6. Enter the LRN if the applicant has one. It must be exactly 12 digits; the
   field will not accept anything else.
7. Enter the Birthdate.
8. Select the Sex.
9. Enter the home Address.
10. Enter a Contact Number if the family has one.
11. Enter the Guardian Name, their Relationship to the student, and their
    Contact number.
12. If the applicant came from another school, enter the Previous School.
13. Tick the checkboxes for documents already submitted: PSA Birth
    Certificate, Report Card, and Good Moral Character. Leave unticked what
    has not been received yet.
14. Review everything, then select Submit Application.

The browser checks required fields before sending; if something required is
empty, it points at the field. After a successful submission, the system
records the application and takes you to the application's own page, showing
the message: Application ADM-2026-0001 encoded successfully. The number in the
message is the application number assigned to this application. Write it down
or note it; it identifies this application from now on.

### 12.3 Reviewing an Application

Status: VERIFIED WORKING

From the Admissions list, find the application and select View in its row.
The application page shows everything that was entered, the documents
submitted, and the current status. If the status is Pending, two buttons
appear at the bottom: Approve and Enroll, and Reject Application.

### 12.4 Approving an Application

Status: VERIFIED WORKING

1. Open the pending application as described above.
2. Check the details and the document checklist.
3. Select Approve and Enroll.

The system creates the student record, assigns a student ID such as
TRAC-2026-0001, creates an enrollment for the active school year, and takes
you to the new student's record page with the message: Application approved.
Student ID: TRAC-2026-0001. From that page you can go straight to entering
the SF10 grades.

Approving cannot be undone from the screen. If an application was approved by
mistake, the registrar must correct the records manually; contact the system
maintainer for help before doing so.

The system refuses to approve in two situations, showing a message instead:
when a student with the same LRN already exists (A student with this LRN
already exists. Resolve the duplicate before approving.), and when the student
already has an open incoming transfer request.

### 12.5 Rejecting an Application

Status: VERIFIED WORKING

Open the pending application and select Reject Application. The status changes
to rejected and the message Application has been rejected. appears. No student
record is created. The application remains in the list with its rejected
status for the record.

## 13. Student Records Journey

### 13.1 Finding Students

Status: VERIFIED WORKING

Select Students in the sidebar. The page lists students with filters for a
search term, status, school year, and grade. The status values are active,
transferred, graduated, and dropped. On a phone the table scrolls sideways
inside its frame; swipe to see the other columns.

### 13.2 Opening a Student Record

Status: VERIFIED WORKING

Select a student in the list to open their record page. The page shows the
student's profile, guardian information, and enrollment history, and provides
links for Academic Record, SF10, Edit, Print, and Status. If you open a
student address that does not exist, the system returns you to the list with
the message: Student record not found.

### 13.3 Editing a Student

Status: IMPLEMENTED / CODE-VERIFIED

Select Edit on the student's page to correct profile details. The form carries
the same fields as the admission form plus a Status dropdown with active,
transferred, graduated, and dropped. Save your changes before leaving the
page. This screen exists and follows the same patterns as the verified forms,
but a full edit was not demonstrated at this revision, so review your change
after saving.

### 13.4 Changing a Student's Status

Status: IMPLEMENTED / CODE-VERIFIED

Select Status on the student's page to move a student between active,
transferred, graduated, and dropped. Some transitions are marked registrar
only. As above, the screen exists but was not demonstrated at this revision.

## 14. Academic Records Journey

Status: VERIFIED WORKING, with one caution about validation feedback

The academic record holds the year-level summary: general average,
promotional status, attendance, and awards.

1. Open the student's record page.
2. Select Academic Record. The Academic Record page opens with the student's
   name at the top.
3. Select the School Year (defaults to the active year) and the Grade Level.
4. Enter the General Average, a number from 0 to 100.
5. Select the Promotional Status: Promoted, Retained, or Incomplete.
6. Enter the Attendance Days, a whole number.
7. Enter Awards and Record Notes if any.
8. Select Save Record.

On success the system shows: Academic record saved. and returns you to the
student's record page.

Caution: if the School Year or Grade Level is left unselected, the save
quietly does nothing. The page returns without any success or error message.
If nothing seems to happen after selecting Save Record, check that both the
School Year and the Grade Level are selected, then save again. This missing
error message is a known defect; the save itself works once the required
selections are made.

## 15. SF10 Permanent Record Journey

### 15.1 Entering SF10 Grades

Status: VERIFIED WORKING

1. Open the student's record page.
2. Select SF10. The SF10 entry page opens for that student.
3. Select the School Year and Grade Level for the grades you are entering.
4. For each learning area, enter the four quarterly grades and the final
   grade. The learning areas are Filipino, English, Mathematics, Science,
   Araling Panlipunan, Edukasyon sa Pagpapakatao, MAPEH, and Technology and
   Livelihood Education. Remarks can be entered per area.
5. Select Save.

The system shows: SF10 grades saved. General average: 88. The general average
is computed for you from the final grades. Grades already saved appear
pre-filled when you return; change any value and save again to update it.

### 15.2 Viewing and Printing the SF10 Report

Viewing is VERIFIED WORKING. Printing is KNOWN BROKEN.

To view, open Reports in the sidebar, select SF10-JHS Permanent Record, and
choose the student, school year, and grade level. The permanent record
renders on screen with every learning area, the quarterly and final grades,
and the computed general average.

Do not use the browser's print function for official copies yet. At this
revision, printing a report page includes the dark sidebar and top bar in the
printout. Until this is fixed, keep official copies as on-screen reviews or
export the data another way, and raise the printing fix with the system
maintainer. This applies to all four report types.

## 16. Enrollment Journey

Status: VERIFIED WORKING (viewing)

Select Enrollments in the sidebar. The page lists enrollments for the active
school year with a filter for All, Assigned, or Unassigned, and the usual
search and page controls. Each row shows the student, grade, section, and a
Manage link to the assignment page. The Unassigned filter is how you find
students who still need a section.

## 17. Section Assignment Journey

Current status: KNOWN BROKEN

The intended workflow is:

1. Open Enrollments and select the Unassigned filter.
2. Select Manage on the enrollment that needs a section.
3. Choose a section from the Assign Section list. The list only offers
   sections for that grade level.
4. Select Save Assignment.

Current behavior:

The system rejects the save and returns you to the Dashboard with the
message: Your session token expired. Please try again. The section is not
assigned. This happens every time, for every user.

Result:

Enrollments cannot be assigned sections through the screen. The Dashboard's
Unassigned Sections count will keep growing.

User action:

Do not retry the operation; it will fail the same way every time. Record the
needed assignments on paper or in a spreadsheet until the software fix is
deployed. The fix is a small known change; raise it with the system
maintainer.

## 18. Transfer Journey

Transfers track the movement of SF10 records in and out of the school, with a
30-day deadline from the student's first attendance at the receiving school,
following DepEd Order 54, s. 2016.

### 18.1 Viewing Transfers

Status: VERIFIED WORKING

Select Transfers in the sidebar. The page shows the DepEd policy notice with
an overdue count, filter tabs for All, Incoming, and Outgoing, and the table
of requests. Each row shows the student, direction, counterpart school,
dates, the days remaining or overdue, the status, and a Manage link.

One visual defect to know about: the colored status badges in the table
(Overdue, days left) currently have no background color, so on the light table
they appear as faint text. The status is still readable in the Status column.
This is cosmetic and does not affect the data.

### 18.2 Creating a Transfer Request

Current status: KNOWN BROKEN

The intended workflow is:

1. Open Transfers and select New Request.
2. Select the Student from the list.
3. Select the Direction: Incoming means requesting the SF10 from the previous
   school; Outgoing means releasing the SF10 to the receiving school.
4. Enter the Counterpart School.
5. Enter the Request Date and the First Attendance Date. The 30-day due date
   is calculated and shown automatically.
6. Enter Notes if needed.
7. Select Create Request.

Current behavior:

The system rejects the submission and returns you to the Dashboard with the
message: Your session token expired. Please try again. No request is created.

User action:

Do not retry. Record transfer requests outside the system until the fix is
deployed.

### 18.3 Updating a Transfer Request

Current status: KNOWN BROKEN

Opening a request with Manage shows the request details and an Update Status
panel with buttons such as Mark Documents Sent, Mark SF10 Received, Mark
Completed, Escalate to SGOD, and Reopen, depending on the direction and
current status. At this revision, selecting any of these buttons produces the
same session-token-expired rejection described above. The transfer workflow
cannot currently be operated through the screen. The dashboard and
notifications still show overdue transfers, but they cannot be actioned until
the fix is deployed.

## 19. Search Journey

Status: VERIFIED WORKING

1. Select Search in the sidebar.
2. Type part of a name or a student number into the Search box.
3. Optionally narrow by Status.
4. Select Search.

Matching students appear in the table with links to their records. Searching
is safe: anything you type is treated as text, never as code.

## 20. Reports Journey

Status: On-screen generation VERIFIED WORKING; printing KNOWN BROKEN

Select Reports in the sidebar. Four report cards are shown.

Enrollment Summary: counts of enrollments per grade level for a chosen year.
Admission Status Report: admission counts by status for a chosen year.
Student Master List: the full list of students for a chosen year.
SF10-JHS Permanent Record: the permanent record for one student, year, and
grade.

For each, select the needed choices and select Generate Report. The report
renders on screen. As noted in section 15.2, do not print from the browser
until the print fix is deployed.

## 21. LIS CSV Journey

Status: VERIFIED WORKING (registrar only)

The LIS area handles DepEd Learner Information System CSV files, aligned to
the SF1 School Register.

### 21.1 Exporting

1. Select LIS in the Administration group.
2. Under Export to LIS CSV, choose the school year and any grade or section
   filters.
3. Select export. The browser downloads a CSV file named like
   LIS_SF1_TRAC_JHS_20252026_20260923.csv.

### 21.2 Downloading the Template

On the same page, the template link downloads a CSV with the correct column
headers, ready to fill in.

### 21.3 Importing

1. Under Import from CSV, choose the filled-in CSV file.
2. Select import. The system processes the file and reports the result, for
   example: Import complete: 0 created, 2 updated, 0 skipped, 0 errors.

Import matches rows to students by LRN or student number and updates existing
records rather than duplicating them, so importing the same file twice is
safe. Rows with problems are listed with reasons; fix them in the CSV and
import again.

### 21.4 LIS Settings

The school's six-digit EBEIS School ID and the Schools Division Office are
set under Settings in the Administration group, in the LIS Export Settings
panel. The ID is required for exports.

## 22. User Management Journey

Status: IMPLEMENTED / CODE-VERIFIED (registrar only)

Select Users in the Administration group. The page lists staff accounts with
their roles and status, and provides a Create User form: username, full name,
password, and role (registrar or encoder). Selecting Create User adds the
account; duplicate usernames are refused with Username already exists.

Each account row has an Enable or Disable button to switch the account on or
off, and a control to clear the failed-sign-in counter for that user, which
unlocks someone who has been throttled by repeated failed sign-ins. These
controls exist in the system and follow the same patterns as the verified
forms, but were not demonstrated at this revision; review the result message
after using them.

## 23. School Year and Section Management Journey

Selecting the active school year is VERIFIED WORKING. Creating a new school
year or section is KNOWN BROKEN.

To switch the active school year, use the school year selector in the top
bar: open it and select the year. The change applies immediately; note that
you land on the Dashboard afterward. The same can be done from the Settings
page with the Set Active button beside a year.

Creating a new school year: on the Settings page, the Add School Year form
takes a label such as 2027-2028, start and end dates, and an Active checkbox.
At this revision, selecting Add School Year is rejected with the
session-token-expired message and nothing is saved.

Creating a new section: the Add Section form takes a grade level and a
section name. Selecting Add fails the same way.

User action for both: do not retry. Ask the system maintainer to apply the
fix, or have the year or section added directly in the database by a
technical maintainer.

The Settings page also holds the LIS Export Settings panel described in
section 21.4, which saves correctly.

## 24. Notifications, Audit, Backup, and Restore

### 24.1 Notifications

Status: VERIFIED WORKING on desktop

The bell in the top bar shows a red count of overdue transfers. Open it to
see the overdue transfers and recent activity, each entry linking to its
record. On a phone, a tap on a notification sometimes closes the menu without
opening the item; if that happens, open the menu again or navigate from the
sidebar instead. This mobile quirk is under review.

### 24.2 Audit Log

Status: VERIFIED WORKING (registrar only)

Select Audit in the Administration group. The log lists every significant
action with the actor, action, record, details, and time. Filters for entity
type, date range, and user narrow the list. Every admission, approval,
rejection, grade save, sign-in failure, import, backup, and restore attempt
appears here. Use it to answer who did what and when.

### 24.3 Backup

Status: VERIFIED WORKING (registrar only)

1. Select Backup in the Administration group.
2. Select Export Database.

The system writes a backup file of all records to the server and shows:
Database backup created: trac_jhs_backup_2026-09-24_000830.sql. The file is
named with the date and time. Backups accumulate on the server; the Restore
page lists them. Note that the button saves the file on the server; it does
not download a file to your computer.

### 24.4 Restore

Current status: KNOWN BROKEN, and destructive. Do not use.

The Restore page lists the available backups with their sizes and dates, each
with a Restore button behind a warning confirmation. Executing a restore
currently fails partway: it empties the current data, then stops with the
message: Restore failed: There is no active transaction. The result is that
student, admission, enrollment, grade, and audit data is wiped and the backup
is not loaded.

User action: never select Restore. If a restore is genuinely needed, contact
the system maintainer, who can load the backup file into the database
directly. Treat backups as archives that work; treat the Restore button as
out of order until fixed.

## 25. Password Change Journey

Status: VERIFIED WORKING

1. Select Change Password at the bottom of the sidebar.
2. Enter your Current Password.
3. Enter the New Password.
4. Enter the same new password in Confirm Password.
5. Select the save button.

If the current password is wrong, the system shows: Current password is
incorrect. and changes nothing. On success it confirms the change; use the
new password next time you sign in. Every user should do this before working
with real records, and the registrar must do it for both starter accounts on
any new installation.

## 26. Complete Registrar Workflow: One Student, Start to Finish

This is the verified core lifecycle, shown as one continuous journey. Follow
it once and you will have seen most of the system.

1. Sign in. The Dashboard shows one pending admission.
2. Select Admissions, then New Application.
3. Fill the form for the applicant: school year, grade level, type, name,
   birthdate, sex, address, guardian details, and the documents received.
   Select Submit Application. Note the application number in the success
   message.
4. From the Admissions list, select View on the new application. Review the
   details.
5. Select Approve and Enroll. The system creates the student, assigns the
   student ID, creates the enrollment, and opens the student's record page.
   Note the student ID from the message.
6. The student now needs a section. The Enrollments page would be the place,
   but section assignment is currently broken (section 17), so note the
   student for later assignment.
7. On the student's record page, select SF10. Choose the year and grade, then
   enter the quarterly and final grades for each learning area. Select Save
   and confirm the computed general average in the message.
8. Back on the student's record page, select Academic Record. Choose the year
   and grade level, enter the general average, promotional status, and
   attendance. Select Save Record and confirm the success message. Remember
   the caution in section 14 about selecting the grade level first.
9. Select Reports, then SF10-JHS Permanent Record, choose the student, year,
   and grade, and generate the report to review the complete record on
   screen.
10. When done, select Sign Out.

## 27. Complete Encoder Workflow

1. Sign in with the encoder account. The Dashboard appears without the
   Administration group.
2. Record admission applications as described in section 12.
3. Look up students under Students or Search.
4. Enter SF10 grades and academic records under Records.
5. Generate reports under Reports as requested by the registrar.
6. If you need anything in the Administration area, ask the registrar; the
   system will refuse with: Only the School Registrar can perform this
   action.
7. Select Sign Out when finished.

## 28. Mobile User Journey

Status: VERIFIED WORKING (tested at phone width)

The system works on a phone browser. After signing in:

The Dashboard fits the screen without sideways scrolling. The sidebar is
behind the menu button at the top left; tap it to open, tap the dark overlay
or press Escape to close, and tap any page in the list to go there.

Forms such as New Admission fit the screen and work normally. Tables are
wider than the screen, so they scroll sideways inside their own frame; swipe
left and right inside a table to see all columns. The page itself never
scrolls sideways.

The top bar's school year selector and notification bell are present and
usable. Record pages and their buttons are reachable. The one known mobile
quirk is the notification tap described in section 24.1.

## 29. Signing Out

Status: VERIFIED WORKING

Select Sign Out at the bottom of the sidebar. The system ends your session
and returns you to the sign-in page. Always sign out when you leave the
computer, especially on shared machines, since your session stays active
otherwise.

## 30. Accessibility Notes

The following were observed in the running application. The sign-in fields
are labeled and the cursor starts in the username field. Sidebar links show a
visible outline when reached with the keyboard Tab key. The mobile menu can
be closed with the Escape key. The system respects the reduced-motion
setting where your device or browser offers it. Dashboard numbers animate
from zero, so screen readers may first announce zero; the settled number is
correct. Some forms use visible labels that screen readers may not announce
per field, so verify entries visually after filling a form. There is
currently no skip-to-content link on staff pages. These are improvement
items for a future update, not things you can change.

## 31. Common Problems and What To Do

| What you see | What it means | What to do |
|---|---|---|
| Your session token expired. Please try again. after a save | You used one of the currently broken forms: section assignment, transfer create or update, add school year, add section | Do not retry. Note the work for later and see the relevant section above |
| Invalid credentials or inactive account. | Wrong username or password, disabled account, or temporary throttling after failed attempts | Check your typing; if throttled, wait or ask the registrar to clear the counter on the Users page |
| Current password is incorrect. | Wrong current password on the password change screen | Re-enter your current password |
| Student record not found. | The record address is invalid or the student does not exist | Go back to the Students list and open the student from there |
| No admission applications match the current filters. | Filters are excluding everything | Select Clear filters |
| Nothing happens when selecting Save Record on the academic record | Required School Year or Grade Level not selected, and the error message is missing | Select both, then save again |
| Database Unavailable page | The server cannot reach its database | Wait a few minutes and reload; if it persists, tell the system administrator |
| Report prints with the dark sidebar | Known print defect | Do not browser-print reports; use on-screen review until fixed |
| Restore failed: There is no active transaction. | The known destructive restore defect | Stop immediately. Contact the system maintainer; do not retry |
| A phone tap on a notification closes the menu | Known mobile quirk | Open the menu again or navigate from the sidebar |
| Only the School Registrar can perform this action. | An encoder opened an administration page | Expected; ask the registrar |
| Cannot reach the site at all | Server down, wrong address, or network problem | Confirm the address, check you are on the school network, and tell the system administrator |

## 32. Security and Data Handling Rules

1. Never share your password with anyone, including colleagues. Accounts are
   individual so the audit log stays truthful.
2. Change your password the first time you sign in, and change it again
   whenever you suspect it is known. Section 25 explains how.
3. The registrar must change both starter passwords on any new installation
   before real data is entered.
4. Sign out when you leave the computer, especially on shared machines.
5. Use only your own assigned account.
6. Do not put student information into unrelated websites, messaging apps, or
   AI tools. Records stay in the system.
7. If you believe a password or record has been exposed, tell the registrar
   immediately.
8. The system runs on the school's trusted network without encryption
between devices; keep the office network restricted to staff.

## 33. Feature Status Reference

| Feature | Who | Status |
|---|---|---|
| Sign in and sign out | All | VERIFIED WORKING |
| Dashboard statistics and quick actions | All | VERIFIED WORKING |
| Admission create, view, approve and enroll, reject | Registrar and encoder | VERIFIED WORKING |
| Student list, view, search | All | VERIFIED WORKING |
| Student edit and status change | Registrar | IMPLEMENTED / CODE-VERIFIED |
| Academic record save | Registrar | VERIFIED WORKING, with validation-feedback caution |
| SF10 grade entry | Registrar | VERIFIED WORKING |
| SF10 and other reports on screen | Registrar | VERIFIED WORKING |
| Report printing | Registrar | KNOWN BROKEN |
| Enrollment list | All | VERIFIED WORKING |
| Section assignment | Registrar and encoder | KNOWN BROKEN |
| Transfer list and badges | All | VERIFIED WORKING, badges invisible |
| Transfer create | Registrar and encoder | KNOWN BROKEN |
| Transfer status updates | Registrar and encoder | KNOWN BROKEN |
| Search | All | VERIFIED WORKING |
| School year switching | Registrar | VERIFIED WORKING |
| Add school year | Registrar | KNOWN BROKEN |
| Add section | Registrar | KNOWN BROKEN |
| LIS export, template, import, settings | Registrar | VERIFIED WORKING |
| User create, enable, disable, throttle clear | Registrar | IMPLEMENTED / CODE-VERIFIED |
| Audit log | Registrar | VERIFIED WORKING |
| Notifications | All | VERIFIED WORKING on desktop, mobile quirk |
| Backup creation | Registrar | VERIFIED WORKING |
| Backup restore | Registrar | KNOWN BROKEN, destructive |
| Password change | All | VERIFIED WORKING |
| Mobile use | All | VERIFIED WORKING |
| Public inquiry form | Public | KNOWN BROKEN |
| User Guidelines public page | Public | VERIFIED WORKING |

## 34. Current System Limitations

At revision fe4b778, the system cannot do the following through the screen,
regardless of user or role:

1. Assign sections to enrollments.
2. Create transfer requests or change their status.
3. Add a school year or a section.
4. Print clean official reports from the browser.
5. Restore from a backup without destroying current data.
6. Accept public inquiries through the landing page form.

All six stem from small, identified software defects, not from user error or
configuration. Until fixes are deployed, use the workarounds in the sections
above, and treat any instruction to use these features as superseded by this
guide.

## 35. Verification Method

The statements in this guide come from two sources: reading the system's
code at revision fe4b778, and operating the running application in a real
browser with seeded records, both staff accounts, a phone-sized screen, and
deliberate error attempts. Workflows marked VERIFIED WORKING were performed
end to end. Workflows marked IMPLEMENTED / CODE-VERIFIED exist in the code
and follow the system's standard patterns but were not performed at this
revision. Workflows marked KNOWN BROKEN were attempted and observed failing
in the described way.

Evidence baseline: fe4b778.
