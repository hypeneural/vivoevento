@echo off
powershell -ExecutionPolicy Bypass -File "%~dp0setup-cloudflare-named-webhook-tunnel.ps1" %*
