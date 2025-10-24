# FlexiAPI Cache Fix Instructions

## 🚨 IMPORTANT: Cache Issue Resolution

If you're seeing double banners or old version numbers (v3.3.0, vv3.7.3, etc.), this is due to Composer's aggressive caching. Follow these steps:

### 📦 For New Projects (FAST - RECOMMENDED):

```bash
# 1. Clear Composer cache first
composer clear-cache

# 2. Create project with cache bypass (FAST method)
composer create-project uptura-official/flexiapi:^3.7.15 my-project --no-cache

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

# 3. Reinstall fresh (FAST)
composer install --no-cache
```

### 🏥 Nuclear Option (If Still Having Issues):

```bash
# Clear EVERYTHING Composer related
composer clear-cache --all
composer global clear-cache

# Remove global Composer cache directory manually
# Windows: rmdir /s %APPDATA%\Composer\cache
# Linux/Mac: rm -rf ~/.composer/cache

# Then create fresh project
composer create-project uptura-official/flexiapi:^3.7.15 my-project --no-cache
```

### ⚠️ DON'T USE --prefer-source (TOO SLOW):
```bash
# ❌ AVOID THIS - Takes 30+ minutes
composer create-project uptura-official/flexiapi my-project --prefer-source
```

### ✅ Expected Result:

After following these steps, you should see:
```
FlexiAPI CLI v3.7.15 - Rapid API Development Framework
```

**Single clean banner, no duplicates, correct version!**

### 🔧 Technical Details:

This version (3.7.15+) includes:
- Balanced cache configuration (fast but fresh)
- Cache TTL set to 5 minutes for repo metadata
- Auto-clears cache on install/update  
- Dist-based installation (fast downloads)
- Timestamp-based cache invalidation