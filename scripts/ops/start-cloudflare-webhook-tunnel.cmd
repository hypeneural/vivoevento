@echo off
powershell -ExecutionPolicy Bypass -File "%~dp0start-cloudflare-webhook-tunnel.ps1" %*
