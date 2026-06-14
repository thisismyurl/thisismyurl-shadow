# Per-Environment Readiness Policies for Shadow by Christopher Ross

**Last Updated:** April 3, 2026  
**Audit Response:** Phase 9 - Environment-Aware Curation  
**Purpose:** Customize diagnostic scope and aggressiveness based on WordPress environment

---

## Philosophy

What you diagnose should match your environment:

- **Production:** Conservative (only proven, high-confidence checks)
- **Staging:** Thorough (include experimental, validate before production)
- **Development:** Aggressive (catch everything, learn from placeholder diagnostics)

Each environment gets a preconfigured policy, but admins can override.

---

## Environment Detection

### Automatic Detection

Use WordPress `WP_ENVIRONMENT_TYPE` constant (WordPress 5.5+):

```php
$environment = wp_get_environment_type(); // 'production', 'staging', 'development', 'local'
```

### Fallback Detection (Pre-5.5)

```php
class Environment_Detector {
    public static function detect() {
        // Check WP_ENVIRONMENT_TYPE (5.5+)
        if (defined('WP_ENVIRONMENT_TYPE')) {
            return WP_ENVIRONMENT_TYPE;
        }
        
        // Check common indicators
        $indicators = [
            'production' => [
                'defined("WP_CACHE")',
                '!ini_get("display_errors")',
                'DISALLOW_FILE_EDIT === true',
            ],
            'staging' => [
                'strpos(home_url(), "staging") !== false',
                'strpos(home_url(), "staging.") === 0',
                'strpos($_SERVER["HTTP_HOST"], "staging") !== false',
            ],
            'development' => [
                'defined("WP_DEBUG") && WP_DEBUG',
                'ini_get("display_errors") === "1"',
                'file_exists(".git") in ABSPATH',
            ],
        ];
        
        foreach ($indicators as $env => $checks) {
            if (count(array_filter($checks, fn($c) => eval("return $c;"))) > 0) {
                return $env;
            }
        }
        
        return 'production'; // Safe default
    }
}
```

---

## Default Policies by Environment

### 🟢 PRODUCTION (Maximum Caution)

**Principle:** Only run high-confidence, safe, proven diagnostics.

**Configuration:**

```php
PRODUCTION_POLICY = [
    'scan_profile'              => 'core_50',
    'confidence_minimum'        => 90,
    'readiness_states_allowed'  => ['production'],
    'auto_fix_enabled'          => true,
    'auto_fix_confidence_min'   => 90,
    'schedule'                  => 'weekly',
    'alert_on_critical'         => true,
    'alert_on_warning'          => false,
    'share_with_external_audit' => false,
];
```

**Diagnostics Included:** 35 high-confidence essential checks

**Example Workflow:**
```
Weekly Scan (Production Policy):
├─ Runs: Core 50 (35 high-confidence checks)
├─ Auto-fixes: Only if confidence ≥90 and safe
├─ Results: Critical issues reported immediately
├─ Advanced panel: Not shown; admins must opt-in
└─ Export: Internal only, not shared
```

**Recommended Settings:**
```
Settings → Diagnostics
├─ Scan Profile: Core 50 (Production Default)
├─ Auto-Fix: ☑ High-Confidence Only
├─ Schedule: ☑ Weekly
├─ Notifications: ☑ Critical Only
└─ Share Results: ☐ Not enabled
```

---

### 🟡 STAGING (Balanced Coverage)

**Principle:** Test everything thoroughly before it reaches production.

**Configuration:**

```php
STAGING_POLICY = [
    'scan_profile'              => 'full_scan',
    'confidence_minimum'        => 70,
    'readiness_states_allowed'  => ['production', 'beta'],
    'auto_fix_enabled'          => false,  // Manual review before fix
    'auto_fix_confidence_min'   => false,  // Disabled
    'schedule'                  => '2x weekly',
    'alert_on_critical'         => true,
    'alert_on_warning'          => true,
    'share_with_external_audit' => true,  // Safe to share
];
```

**Diagnostics Included:** 113 high + medium confidence checks (excluding low/experimental)

**Example Workflow:**
```
Twice-Weekly Scan (Staging Policy):
├─ Runs: Core 50 + Advanced (113 checks total)
├─ Auto-fixes: Disabled; manual review required
├─ Results: All issues reported (critical + warnings)
├─ Advanced panel: Fully enabled
├─ Export: JSON/CSV for external audit or documentation
└─ Filter: Beta checks included (not production-only)
```

**Recommended Settings:**
```
Settings → Diagnostics
├─ Scan Profile: Full Scan (Staging Default)
├─ Auto-Fix: ☐ Disabled (Manual Review Required)
├─ Schedule: ☑ Twice Weekly (Mon & Thu)
├─ Notifications: ☑ All Issues
├─ Share Results: ☑ Enabled (For audit trail)
└─ Filter: Show production + beta
```

**Staging-Specific Workflow:**
```
Before Deploying to Production:
1. Run Staging Scan (catches all issues)
2. Review Medium-Confidence findings (may be false-positives)
3. Resolve Critical issues before deployment
4. Document Medium/Low issues (decide if needed in production)
5. Export results for release notes
```

---

### 🔴 DEVELOPMENT (Aggressive Learning)

**Principle:** Catch everything, including experimental. Learn about what's coming.

**Configuration:**

```php
DEVELOPMENT_POLICY = [
    'scan_profile'              => 'include_experimental',
    'confidence_minimum'        => 0,  // All checks, even unproven
    'readiness_states_allowed'  => ['production', 'beta', 'planned'],
    'auto_fix_enabled'          => false,
    'auto_fix_confidence_min'   => false,  // All auto-fix disabled
    'schedule'                  => 'daily',
    'alert_on_critical'         => true,
    'alert_on_warning'          => true,
    'alert_on_info'             => true,
    'share_with_external_audit' => false,  // Often WIP
];
```

**Diagnostics Included:** All 230 diagnostics including experimental

**Example Workflow:**
```
Daily Scan (Development Policy):
├─ Runs: All 230 checks (including experimental)
├─ Auto-fixes: Disabled; experimental not auto-fixed ever
├─ Results: All issues + informational messages shown
├─ Advanced panel: Fully enabled + experimental tier
├─ Diagnostics shown:
│  ├─ High-Confidence (🟢 35 checks)
│  ├─ Medium-Confidence (🟡 78 checks)
│  ├─ Low-Confidence (🟠 22 checks)
│  └─ Experimental (🔴 95 checks - learn what's planned)
└─ Export: Typically not shared (work-in-progress)
```

**Recommended Settings:**
```
Settings → Diagnostics
├─ Scan Profile: Advanced + Experimental (Developer Default)
├─ Auto-Fix: ☐ Disabled (Manual Only)
├─ Schedule: ☑ Daily
├─ Notifications: ☑ All (Critical, Warnings, Info)
├─ Show Experimental: ☑ Yes (Learn what's coming)
└─ Filter: Show production + beta + planned
```

**Development-Specific Workflow:**
```
On Commit:
- Run daily scan
- Review experimental checks (roadmap items)
- Understand Medium-Confidence findings (may catch bugs)

When Creating Patch:
- Resolve High-Confidence issues first
- Address Medium-Confidence if appropriate
- Avoid Low-Confidence (often false-positives in dev)

When Reviewing Merge Request:
- Ensure Core 50 passing in staging scan
- Document Medium-Confidence decisions
- Note if experimental findings relevant
```

---

### 🟤 LOCAL (Unrestricted)

**Configuration:**

```php
LOCAL_POLICY = [
    'scan_profile'              => 'include_experimental',
    'confidence_minimum'        => 0,
    'readiness_states_allowed'  => ['production', 'beta', 'planned'],
    'auto_fix_enabled'          => true,  // Can be freely tested
    'auto_fix_confidence_min'    => 50,  // Even low-confidence can be tested
    'schedule'                  => 'on-demand',
    'alert_on_critical'         => false,
    'share_with_external_audit' => false,
];
```

**Diagnostics Included:** All 230 diagnostics including experimental

**Expected Use:** Developers testing locally; auto-fix safe to experiment with

---

## Implementation

### 1. Readiness System Enhancement

Add environment-aware policy to Readiness_Registry:

```php
class Readiness_Registry {
    
    // Apply environment policy to filtering
    public function get_allowed_readiness_states($context = null) {
        $environment = Environment_Detector::detect();
        
        $policies = [
            'production'  => ['production'],
            'staging'     => ['production', 'beta'],
            'development' => ['production', 'beta', 'planned'],
            'local'       => ['production', 'beta', 'planned'],
        ];
        
        $policy_states = $policies[$environment] ?? $policies['production'];
        
        // Apply filter hook for override
        return apply_filters(
            'thisismyurl_allowed_readiness_states',
            $policy_states,
            $environment,
            $context
        );
    }
    
    // Get diagnostics filtered by environment
    public function discover_by_environment() {
        $environment = Environment_Detector::detect();
        $allowed_states = $this->get_allowed_readiness_states();
        
        // Auto-adjust confidence for environment
        $confidence_minimums = [
            'production'  => 90,
            'staging'     => 70,
            'development' => 0,
            'local'       => 0,
        ];
        
        $min_confidence = $confidence_minimums[$environment];
        
        return $this->discover_diagnostics_by_criteria(
            readiness_states: $allowed_states,
            confidence_minimum: $min_confidence
        );
    }
}
```

### 2. Admin Settings Integration

New section: Settings → Diagnostics → Environment Policy

```
CURRENT ENVIRONMENT
├─ Detected: Production (from WP_ENVIRONMENT_TYPE constant)
├─ Override: Select environment manually [Dropdown]
└─ Auto-Detect: ☑ Enabled

POLICY FOR [PRODUCTION]
├─ Scan Profile: Core 50 (Production Default)
│  └─ Includes: 35 high-confidence essential checks
│
├─ Auto-Fix: ☑ High-Confidence Only
│  └─ Safety: Minimum confidence required for auto-fix
│
├─ Schedule: Weekly
│  └─ Next run: 2026-04-10, 03:00 UTC
│
├─ Notifications:
│  ├─ ☑ Critical Issues (always)
│  ├─ ☐ Warnings (not in production)
│  └─ ☐ Info Messages (not in production)
│
└─ Readiness Filter:
   └─ Show only production-ready diagnostics ☑

[Save Policy] [Reset to Default] [Show all environments]
```

### 3. Environment Indicator Dashboard

Add to Shadow by Christopher Ross dashboard header:

```
═══════════════════════════════════════════════════════
 Shadow Dashboard
 
 Environment: 🟢 PRODUCTION (WP_ENVIRONMENT_TYPE)
 Policy: Core 50 (35 essential checks)
 Last Scan: 2026-04-03, 03:00 UTC
 Auto-Fix: Enabled (≥90% confidence only)
═══════════════════════════════════════════════════════

Next Scan: 2026-04-10, 03:00 UTC
Current Issues: 0 Critical, 0 Warnings
Status: ✅ All systems optimal
```

### 4. Policy Override Filter Hook

Allow custom policies via code:

```php
// Example: Use different policy for multisite subsites

add_filter('thisismyurl_allowed_readiness_states', function($states, $env) {
    // All subsites scan more conservatively
    if (is_multisite() && !is_main_site()) {
        return ['production']; // Subsites only get core 50
    }
    return $states;
}, 10, 2);

add_filter('thisismyurl_confidence_minimum', function($min, $env) {
    // Staging scans every 6 hours instead of twice-weekly
    if ($env === 'staging') {
        wp_schedule_event(time(), 'every_6_hours', 
            'thisismyurl_run_diagnostic_scan');
    }
    return $min;
}, 10, 2);

add_filter('thisismyurl_auto_fix_enabled', function($enabled, $env) {
    // Development environment: allow all auto-fixes for testing
    if ($env === 'local' || $env === 'development') {
        return true; // Safe in development
    }
    return $enabled;
}, 10, 2);
```

### 5. AJAX Endpoint: Get Environment Policy

```php
class AJAX_Environment_Policy {
    
    public function handle_request() {
        check_ajax_referer('thisismyurl_nonce');
        
        $environment = Environment_Detector::detect();
        
        wp_send_json_success([
            'environment'           => $environment,
            'readiness_allowed'     => Readiness_Registry::get_allowed_readiness_states(),
            'confidence_minimum'    => $this->get_confidence_minimum(),
            'auto_fix_enabled'      => $this->is_auto_fix_enabled(),
            'scan_profile'          => $this->get_scan_profile(),
            'schedule'              => $this->get_schedule(),
            'policy_description'    => $this->describe_policy(),
        ]);
    }
}
```

---

## Deployment Checklist

### Pre-Production Deployment

```
☐ Switch environment to Staging
  ├─ Set WP_ENVIRONMENT_TYPE to 'staging' in wp-config.php
  ├─ Run full diagnostics scan (113 checks, not just core 50)
  ├─ Fix all Critical issues
  ├─ Review all Medium-Confidence warnings
  └─ Document any Low-Confidence issues for monitoring

☐ Stage Verification Complete
  └─ Export governance report for audit trail

☐ Switch to Production
  ├─ Update WP_ENVIRONMENT_TYPE to 'production'
  ├─ Verify Core 50 policy active (settings page check)
  ├─ Run initial scan (confirm 35 checks only)
  ├─ Monitor first 24 hours (alerting enabled)
  └─ Document any unexpected findings
```

### Ongoing Monitoring

```
PRODUCTION:
├─ Weekly scans (Core 50 only)
├─ Alert on critical only
└─ Quarterly full audit (comparison to baseline)

STAGING:
├─ Twice-weekly full scans
├─ Alert on all issues
└─ Pre-deployment verification step

DEVELOPMENT:
├─ Daily scans (all 230)
├─ Experiment with experimental checks
└─ Inform production decisions
```

---

## Migration: Existing Installations

### For Sites Already Running Shadow by Christopher Ross

On update to environment-policy version:

```php
// Migration logic
if (get_option('thisismyurl_version') < '3.5') {
    $environment = Environment_Detector::detect();
    
    // Load default policy for detected environment
    update_option('thisismyurl_policy_environment', $environment);
    update_option('thisismyurl_policy_preset', 
        $this->get_preset_for_environment($environment));
    
    // One-time notice
    update_option('thisismyurl_show_environment_policy_notice', true);
}
```

**User-Facing Notice:**
```
Shadow by Christopher Ross detected your environment as: PRODUCTION

Policy automatically set to:
├─ Scan Profile: Core 50 (35 essential checks)
├─ Auto-Fix: High-confidence only
├─ Schedule: Weekly
└─ Alerts: Critical issues only

Settings → Diagnostics to customize or override policy.
```

---

## Example Scenarios

### Scenario 1: Pre-Deployment Validation

```
Developer's Workflow:

1. LOCAL (daily):
   ├─ All 230 diagnostics run
   ├─ Experimental checks included
   ├─ Auto-fix enabled for testing
   └─ Results: Understand what's planned

2. DEVELOPMENT (commit):
   ├─ Automated scan on commit
   ├─ Core 50 + Advanced required to pass
   ├─ Medium-Confidence issues reviewed
   └─ Merge approved if staging-ready

3. STAGING (pre-deploy):
   ├─ Full 113-check scan
   ├─ All issues reviewed manually
   ├─ No auto-fixes applied
   ├─ Governance report exported
   └─ Signed off by QA

4. PRODUCTION (deployed):
   ├─ Core 50 scan runs weekly
   ├─ Auto-fix safe (≥90% confidence)
   ├─ Critical alerts only
   └─ Monitoring for unexpected issues
```

### Scenario 2: Regulatory Audit

```
Audit Request: "Show all diagnostics run in your system"

Response by Environment:

PRODUCTION:
- "Core 50 production-ready diagnostics"
- Weekly scans, 0-2 critical issues typically
- All 35 diagnostics high-confidence
- Gov report: [2MB JSON export]

STAGING:
- "113 full checks (includes beta)"
- Pre-deployment validation
- Gov report: [4MB JSON export]

DEVELOPMENT:
- Internal only, not disclosed
- "Private testing environment"

Confidence Score Matrix:
- All 230 diagnostics listed with confidence tier
- High-Confidence diagnostics verified with data
```

---

## Conclusion

**Per-Environment Readiness Policies** address the audit's P1 concern about product scope:

> **Before:** "All 230 diagnostics always; can't disable; overwhelming"  
> **After:** "Production gets Core 50 (essential); staging gets the full set; dev gets experimental (learning)"

This framework enables:
1. **Appropriate scope per context** (conservative in production, thorough in staging)
2. **Reduced noise in production** (Core 50 only, auto-fix only high-confidence)
3. **Better pre-deployment testing** (thorough staging scans catch issues)
4. **Learning/evolution** (development sees roadmap items, informs product decisions)
5. **Audit compliance** (governance report per environment, confidence scoring transparent)

**Combined with Phases 7-8,** Shadow by Christopher Ross now offers:
- ✅ Core 50 for essential needs
- ✅ Confidence scoring for transparency
- ✅ Environment policies for context
- ✅ Per-deployment validation
- ✅ Ruthless curation backed by data

This is the response to "lack of ruthless curation" — we now have *policies-driven* curation that scales.
