# The CCC Employment System Story

## The problem

Running a company's day-to-day HR operations by spreadsheet or paper doesn't
scale: tracking who's employed, who's on leave, who worked which shift, and
what everyone is owed at payroll time becomes error-prone and slow to answer
even simple questions like "how many people are active right now" or "who's
on leave this week."

## What this is

This repository is the **Laravel API** backing CCC's employment system — the
source of truth for employee records, attendance, leave, and payroll-adjacent
data. The `ccc-employment-system-client` (Next.js) web app is the dashboard
that calls into this API; this repo owns the MySQL database and all business
logic/validation.

## What the solution does (expected, fill in as features land)

- **Employees** — records for every employee: personal info, employment
  status, role/department.
- **Attendance** — time in/out tracking, shift assignment.
- **Leave** — requests, approvals, balances.
- **Payroll inputs** — the data payroll processing depends on (hours,
  leave taken, adjustments), even if payroll computation itself lives
  elsewhere.

This section is a placeholder — replace each bullet with what's actually
built as features ship.

## Architecture

Laravel MVC — controllers, Eloquent models, API resources. Chosen for speed
of development (school project) over MVP/MVVM, which fit UI-heavy client
apps, not a backend API framework built around MVC already.
