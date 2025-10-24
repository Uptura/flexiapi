# FlexiAPI Cache Fix Instructions

## 🚨 IMPORTANT: Cache Issue Resolution

If you're seeing double banners or old version numbers (v3.3.0, vv3.7.3, etc.), this is due to Composer's aggressive caching. Follow these steps:

### 📦 For New Projects (RECOMMENDED):

```bash
# 1. Clear ALL Composer caches first
composer clear-cache

# 2. Create project with explicit cache bypass  
composer create-project uptura-official/flexiapi:^3.7.14 my-project --no-cache --prefer-source

# 3. Navigate and test
cd my-project
flexiapi
```

### 🔄 For Existing Projects:

```bash
# 1. Clear caches
composer clear-cache

# 2. Remove vendor and lock
rm -rf vendor composer.lock  # Linux/Mac
# OR
Remove-Item -Recurse -Force vendor, composer.lock  # PowerShell

# 3. Reinstall fresh
composer install --no-cache --prefer-source
```

### 🏥 Nuclear Option (If Still Having Issues):

```bash
# Clear EVERYTHING Composer related
composer clear-cache --all
composer global clear-cache

# Remove global Composer cache directory
# Windows: rmdir /s %APPDATA%\Composer
# Linux/Mac: rm -rf ~/.composer/cache

# Then create fresh project
composer create-project uptura-official/flexiapi my-project --no-cache --prefer-source
```

### ✅ Expected Result:

After following these steps, you should see:
```
FlexiAPI CLI v3.7.14 - Rapid API Development Framework
```

**Single clean banner, no duplicates, correct version!**

### 🔧 Technical Details:

This version (3.7.14+) includes:
- Cache-busting configuration
- Forced cache clearing on install/update  
- Source-based installation (bypasses dist cache)
- Timestamp-based cache invalidation