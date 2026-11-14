GHOREBAIRE FIX - PHP + MYSQL (XAMPP)
====================================

This package preserves the supplied website pages and CSS while connecting technician registration, verification, matching, job acceptance and job status tracking to MySQL.

INSTALLATION
------------
1. Extract GhoreBaireFix_MySQL_PHP into C:\xampp\htdocs\
2. Start Apache and MySQL in XAMPP.
3. Open http://localhost/phpmyadmin and import database.sql.
4. Open http://localhost/GhoreBaireFix_MySQL_PHP/index.html
5. Create a customer account and a technician account for testing.
6. Create an admin as described below to approve technician accounts.

DATABASE
--------
Database: ghorebaire_fix
MySQL user: root (default XAMPP)
Password: blank (default XAMPP)

TECHNICIAN WORKFLOW
-------------------
1. On Sign Up select Technician. Enter specialty, experience, service areas and upload an NID/trade-license/certificate (PDF/JPG/PNG, max 5 MB).
2. Technician verification starts as Pending and availability starts as Unavailable.
3. Admin logs in and opens Dashboard > Technician Verification Queue. Admin can view the uploaded document and Approve, Reject or Request Resubmission.
4. Approved technicians can set Available or Unavailable.
5. Customers booking a service see only approved, active, available technicians whose specialty and service-area text match the selected category and entered location.
6. The booking is assigned to the selected technician. The technician sees only their own assigned requests and can Accept or Reject.
7. After accepting, the technician sees the customer's phone number and can move the job through On the Way > In Progress > Completed. Customer dashboard displays the current status.
8. An accepted/in-progress job makes the technician Busy. Rejected or completed jobs return availability to Available.

CREATE AN ADMIN ACCOUNT (UPDATED)
---------------------------------
1. Open admin_config.php.
2. Replace CHANGE-THIS-ADMIN-SETUP-CODE with a long private code of your own.
   Do not share this code with customers or technicians.
3. Open login.html > Sign Up > choose Administrator.
4. Enter the private Administrator Registration Code and create the account.
5. Sign in. The Admin Dashboard provides:
   - Technician document review: approve, reject or request resubmission.
   - User management: view all accounts and set Active, Inactive or Blocked.
   - Booking management: view all bookings and update their status.
6. Keep admin_config.php private. If you no longer need administrator registration,
   change the code in that file to a new private value and do not share it.

IMPORTANT: Administrator accounts must only be created by the website owner.
The setup code is a simple protection suitable for this academic XAMPP project.
For a public production website, use a stronger invitation process and HTTPS.


SECURITY NOTES
--------------
- Uploaded verification files are stored under uploads/verification and direct web access is denied by .htaccess. Admins view documents through a session-protected PHP endpoint.
- Passwords are hashed. Technician actions are checked against the logged-in technician ID.
- Customer contact details are hidden from technicians until a job is accepted.
- This is an academic prototype. Configure HTTPS, secure production credentials, backups and a real payment gateway before public deployment.

DATABASE NOTE
-------------
database.sql creates the schema and triggers for a fresh database. If an older database already exists, back it up first. This project update uses the technicians, verification_documents, bookings, booking_status_history and notifications tables from the supplied schema.

KNOWN SCOPE LIMITS
------------------
- Technician category matching uses the specialty text entered at registration. Use clear terms such as appliance repair, electronics/laptop repair or vehicle mechanic.
- Service-area matching uses words in the customer's location and technician's service-area field; it is a basic academic matching rule, not GPS.
- Payment gateway, complaints, review submission and email/SMS delivery are not implemented by this technician-workflow update.


CUSTOMER BOOKING TRACKING
1. Sign in using a CUSTOMER account.
2. Open Dashboard from the navigation bar.
3. The My Bookings section lists each booking ID, service, assigned technician, location, schedule and current status.
4. Status changes made by the assigned technician (Accepted, On the Way, In Progress, Completed) appear when the customer refreshes the dashboard.

BOOKING CONFIRMATION TROUBLESHOOTING
The booking page JavaScript is versioned to prevent an older cached script from being used. If an error appears, hard-refresh the page with Ctrl+F5. The server now identifies which required field was missing instead of showing a generic message. Confirm that you selected a technician in Step 2 and selected both a preferred date and time in Step 3.

PAYMENT AND VOUCHER (SANDBOX)
1. A technician accepts a Requested booking and enters the service charge in BDT.
2. The customer sees Pay Now for that Accepted booking on the customer dashboard.
3. The customer chooses a sandbox method (bKash, Nagad, Card or Bank Transfer) on payment.php.
4. process_payment.php records a successful simulated payment in the existing payments table.
5. voucher.php displays the payment voucher. The customer can print it or open it later from the dashboard.

This is a demonstration-only sandbox flow. It does not connect to a real payment gateway and does not transfer real money. The existing bookings.final_price and payments table are used; no database schema change is required for the supplied database.sql.
