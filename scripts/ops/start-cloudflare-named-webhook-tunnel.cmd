@echo off
powershell -ExecutionPolicy Bypass -File "%~dp0start-cloudflare-named-webhook-tunnel.ps1" %*
