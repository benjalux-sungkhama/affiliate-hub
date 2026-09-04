@echo off
REM ============================================================
REM  AffiliateHub - ตัวรันอัตโนมัติเผยแพร่โพสต์
REM  ให้ Windows Task Scheduler เรียกไฟล์นี้ทุก ๆ 1-5 นาที
REM  ปรับ path ให้ตรงกับที่ติดตั้ง XAMPP ของคุณถ้าต่างจากค่าเริ่มต้น
REM ============================================================

set PHP_EXE=C:\xampp\php\php.exe
set SCRIPT=C:\xampp\htdocs\affiliatehub\cron\scheduler.php

"%PHP_EXE%" "%SCRIPT%"
