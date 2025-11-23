# Tests

## Test API Key

Run this simple test to verify your Groq API key is working:

```bash
php tests/test_api_key.php
```

**Expected output:**
- ✅ API key found and format looks correct
- ✅ API is working!
- ✅ SUCCESS: API key is valid and working!

**If you get errors:**
- ❌ No API key found → Add `GROQ_API_KEY=gsk_your_key_here` to `.env` file
- ❌ Invalid API key (401) → Check your API key is correct
- ❌ Insufficient credits (402) → Add credits to your Groq account

