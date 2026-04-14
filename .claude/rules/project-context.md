# Project Context

## What is BugSayMis?
An HR and Academic MIS for Philippine Science High School – Caraga Region Campus (PSHS-CRC).
Users include administrators, HR officers, faculty, staff, students, and parents.

## Active Modules
- HR: Leave applications, DTR/attendance, biometric sync, employee schedules
- Payroll: Payroll runs, payslips, deductions, salary schedules
- IPCR/PMS: Performance management for all employee levels
- Faculty Loading: AI-assisted schedule generation, overload computation
- Recruitment: Job postings → applications → interviews → placement
- Rewards & Recognition: Nominations, evaluations, recognition logs
- SALN: Statement of Assets, Liabilities, and Net Worth
- PDS: Personal Data Sheet (CSC form)
- Library: Collections, borrowing, attendance
- Requests: IT, Vehicle, Facility, Service, Messengerial
- Document Tracking: Routing and tracking of official documents
- Chat: Real-time messaging (Echo + Pusher + Soketi)
- Health / Guidance: Consultation records

## Key People / Roles in the System
- **Administrator** (SuperAdmin) — full access, bypasses all permission checks
- **HR Officer** — `hr.employees.manage`, `hr.leave.approve`
- **Division Chief** — approves leave for their division
- **Faculty** — submits leave, DTR, IPCR, loads
- **Staff** — submits leave, DTR, service requests

## Philippine-Specific Rules
- Leave types follow CSC (Civil Service Commission) Form No. 6 — Revised 2020
- Salary grades follow the Salary Standardization Law (SSL) schedule
- Dates displayed in Philippine locale (en-PH)
- Currency is Philippine Peso (₱)
- Work week: Monday–Friday (weekends excluded from leave computation)

## Infrastructure
- Docker Compose setup in `/Users/junlou/bugsaymis-docker/`
- Services: `php` (Laravel), `mysql`, `nginx`, `soketi`, `phpmyadmin`
- PHP service path: `/var/www/html/bugsaymis`
- Public URL in development: `http://localhost:8080`
- PhpMyAdmin: `http://localhost:8081`
