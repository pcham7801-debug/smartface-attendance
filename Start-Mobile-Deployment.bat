@echo off
title SmartFace Attendance - Cloudflare High-Speed Mobile Server
color 0b
echo ==========================================================
echo   SmartFace Attendance - Cloudflare Live Mobile Server
echo ==========================================================
echo.
echo Starting direct Cloudflare HTTPS tunnel (No password required)...
echo (Keep this window open while testing on your mobile phone)
echo.
D:\xampp\cloudflared.exe tunnel --url http://localhost:80
pause
