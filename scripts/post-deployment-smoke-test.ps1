# SFU MSA Digital Platform — Post-Deployment Smoke Test (PowerShell)
# Run by the operator after cPanel release to verify all three endpoints live.

[CmdletBinding()]
param(
    [string]$MainHost = "https://sfumsa.ca",
    [string]$ApiHost = "https://api.sfumsa.ca",
    [string]$ConnectHost = "https://connect.sfumsa.ca",
    [string]$ProjectsHost = "https://projects.sfumsa.ca"
)

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "SFU MSA Platform — Post-Deployment Live Smoke Tests" -ForegroundColor Cyan
Write-Host "Main:     $MainHost" -ForegroundColor Cyan
Write-Host "API:      $ApiHost" -ForegroundColor Cyan
Write-Host "Connect:  $ConnectHost" -ForegroundColor Cyan
Write-Host "Projects: $ProjectsHost" -ForegroundColor Cyan
Write-Host "==========================================================" -ForegroundColor Cyan

$passed = 0
$failed = 0

function Test-HttpEndpoint {
    param(
        [string]$Name,
        [string]$Url,
        [int]$ExpectedStatus = 200,
        [string]$ExpectedContent = $null,
        [hashtable]$Headers = @{},
        [string]$Method = "GET"
    )

    try {
        $req = [System.Net.HttpWebRequest]::Create($Url)
        $req.Method = $Method
        $req.Timeout = 10000
        $req.AllowAutoRedirect = $true

        foreach ($h in $Headers.Keys) {
            if ($h -eq "Origin") {
                $req.Headers.Add("Origin", $Headers[$h])
            } elseif ($h -eq "Accept") {
                $req.Accept = $Headers[$h]
            } else {
                $req.Headers.Add($h, $Headers[$h])
            }
        }

        $res = $req.GetResponse()
        $statusCode = [int]$res.StatusCode
        $reader = New-Object System.IO.StreamReader($res.GetResponseStream())
        $body = $reader.ReadToEnd()
        $reader.Close()
        $res.Close()

        $statusOk = ($statusCode -eq $ExpectedStatus)
        $contentOk = ($ExpectedContent -eq $null -or $body -match $ExpectedContent)

        if ($statusOk -and $contentOk) {
            Write-Host " [PASS] $Name (HTTP $statusCode)" -ForegroundColor Green
            $script:passed++
        } else {
            Write-Host " [FAIL] $Name (HTTP $statusCode, expected $ExpectedStatus)" -ForegroundColor Red
            $script:failed++
        }
    } catch [System.Net.WebException] {
        $webEx = $_.Exception
        if ($webEx.Response) {
            $statusCode = [int]$webEx.Response.StatusCode
            if ($statusCode -eq $ExpectedStatus) {
                Write-Host " [PASS] $Name (HTTP $statusCode)" -ForegroundColor Green
                $script:passed++
                return
            }
            Write-Host " [FAIL] $Name (HTTP $statusCode, expected $ExpectedStatus)" -ForegroundColor Red
        } else {
            Write-Host " [FAIL] $Name : $($webEx.Message)" -ForegroundColor Red
        }
        $script:failed++
    } catch {
        Write-Host " [FAIL] $Name : $($_.Exception.Message)" -ForegroundColor Red
        $script:failed++
    }
}

Write-Host "`n--- 1. Main Website Checks ---" -ForegroundColor Yellow
Test-HttpEndpoint "Main Homepage" "$MainHost/" 200 'id="app"'
Test-HttpEndpoint "Main robots.txt" "$MainHost/robots.txt" 200 "Sitemap:"
Test-HttpEndpoint "Main sitemap.xml" "$MainHost/sitemap.xml" 200 "urlset"

Write-Host "`n--- 2. API Health & CORS Checks ---" -ForegroundColor Yellow
Test-HttpEndpoint "API Health Probe (/up)" "$ApiHost/up" 200
Test-HttpEndpoint "API Public Events Endpoint" "$ApiHost/api/v1/ems/public/events" 200

Write-Host "`n--- 2b. Projects Origin CORS Preflight ---" -ForegroundColor Yellow
function Test-ProjectsPreflight {
    param(
        [string]$Name,
        [string]$Method,
        [string]$Path
    )
    try {
        $url = "$ApiHost$Path"
        $req = [System.Net.HttpWebRequest]::Create($url)
        $req.Method = "OPTIONS"
        $req.Timeout = 10000
        $req.Headers.Add("Origin", $ProjectsHost)
        $req.Headers.Add("Access-Control-Request-Method", $Method)
        $req.Headers.Add("Access-Control-Request-Headers", "authorization,content-type,x-requested-with")
        $res = $req.GetResponse()
        $statusCode = [int]$res.StatusCode
        $acao = $res.Headers["Access-Control-Allow-Origin"]
        $res.Close()
        if (($statusCode -eq 200 -or $statusCode -eq 204) -and $acao -eq $ProjectsHost) {
            Write-Host " [PASS] $Name (HTTP $statusCode + ACAO)" -ForegroundColor Green
            $script:passed++
        } else {
            Write-Host " [FAIL] $Name (HTTP $statusCode; ACAO=$acao)" -ForegroundColor Red
            $script:failed++
        }
    } catch [System.Net.WebException] {
        $webEx = $_.Exception
        if ($webEx.Response) {
            $statusCode = [int]$webEx.Response.StatusCode
            $acao = $webEx.Response.Headers["Access-Control-Allow-Origin"]
            if (($statusCode -eq 200 -or $statusCode -eq 204) -and $acao -eq $ProjectsHost) {
                Write-Host " [PASS] $Name (HTTP $statusCode + ACAO)" -ForegroundColor Green
                $script:passed++
                return
            }
            Write-Host " [FAIL] $Name (HTTP $statusCode; ACAO=$acao)" -ForegroundColor Red
        } else {
            Write-Host " [FAIL] $Name : $($webEx.Message)" -ForegroundColor Red
        }
        $script:failed++
    } catch {
        Write-Host " [FAIL] $Name : $($_.Exception.Message)" -ForegroundColor Red
        $script:failed++
    }
}
Test-ProjectsPreflight "Projects CORS OPTIONS /api/v1/pm/departments" "GET" "/api/v1/pm/departments"
Test-ProjectsPreflight "Projects CORS OPTIONS /api/v1/auth/login" "POST" "/api/v1/auth/login"

Write-Host "`n--- 3. Connect Frontend Checks ---" -ForegroundColor Yellow
Test-HttpEndpoint "Connect Homepage" "$ConnectHost/" 200 'id="app"'
Test-HttpEndpoint "Connect Deep Route (SPA History Mode)" "$ConnectHost/login" 200 'id="app"'
Test-HttpEndpoint "Connect robots.txt" "$ConnectHost/robots.txt" 200 "Disallow: /app/"
Test-HttpEndpoint "Connect Service Worker" "$ConnectHost/sw.js" 200

Write-Host "`n--- 4. Projects Frontend Checks ---" -ForegroundColor Yellow
Test-HttpEndpoint "Projects Homepage" "$ProjectsHost/" 200 'id="app"'
Test-HttpEndpoint "Projects Deep Route (SPA History Mode)" "$ProjectsHost/login" 200 'id="app"'
Test-HttpEndpoint "Projects Manifest" "$ProjectsHost/manifest.webmanifest" 200 "MSA PMS"
Test-HttpEndpoint "Projects Service Worker" "$ProjectsHost/sw.js" 200
Test-HttpEndpoint "Projects Offline Page" "$ProjectsHost/offline.html" 200 "offline"

Write-Host "`n==========================================================" -ForegroundColor Cyan
Write-Host "Smoke Test Results: $passed Passed, $failed Failed" -ForegroundColor $(if ($failed -eq 0) { "Green" } else { "Yellow" })
Write-Host "==========================================================" -ForegroundColor Cyan
