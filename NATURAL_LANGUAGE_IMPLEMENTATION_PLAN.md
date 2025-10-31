# Natural Language Query Feature - Implementation Plan

**Based on:** NATURAL_LANGUAGE_REVIEW_FINDINGS.md  
**Created:** 2025-01-XX  
**Priority:** High (Critical security issues identified)

---

## Overview

This plan addresses 14 issues identified in the Natural Language Query feature review, organized by priority. The plan focuses on fixing critical security vulnerabilities first, then improving functionality, performance, and user experience.

---

## Phase 1: Critical Security Fixes (Must Fix Immediately)

### Task 1.1: Fix XSS Vulnerability in Markdown Rendering
**Priority:** Critical  
**Severity:** Critical  
**Estimated Time:** 1 hour

**Description:**  
Fix the XSS vulnerability where AI responses are rendered using `innerHTML` without sanitization.

**Files to Modify:**
- `assetGo-frontend/src/app/ai-features/components/natural-language/nlq-chat.component.ts`

**Implementation Steps:**
1. Import `DomSanitizer` and `SafeHtml` from `@angular/platform-browser`
2. Inject `DomSanitizer` in constructor
3. Update `formatMessage()` method to return `SafeHtml` and sanitize content
4. Update template to use `[innerHTML]` with sanitized content
5. Test with malicious HTML/JavaScript to verify sanitization works

**Code Changes:**
```typescript
import { DomSanitizer, SafeHtml } from '@angular/platform-browser';

constructor(
  private nlService: NaturalLanguageService,
  private sanitizer: DomSanitizer
) {}

formatMessage(content: string): SafeHtml {
  const formatted = this.nlService.formatMarkdown(content);
  return this.sanitizer.sanitize(SecurityContext.HTML, formatted) || '';
}
```

**Acceptance Criteria:**
- ✅ Markdown content is sanitized before rendering
- ✅ Malicious scripts in AI responses are blocked
- ✅ Safe HTML (bold, italic, lists) still renders correctly
- ✅ No XSS vulnerabilities remain

---

### Task 1.2: Fix Error Message Exposure in Production
**Priority:** Critical  
**Severity:** High  
**Estimated Time:** 30 minutes

**Description:**  
Make error messages conditional on debug mode to prevent information disclosure in production.

**Files to Modify:**
- `app/Http/Controllers/Api/NaturalLanguageController.php`

**Implementation Steps:**
1. Update `getContext()` method error response (line 31)
2. Update `chat()` method error response (line 67)
3. Use `config('app.debug')` to conditionally expose detailed errors
4. Ensure generic messages are user-friendly

**Code Changes:**
```php
// In getContext() method
return response()->json([
    'success' => false, 
    'error' => config('app.debug') 
        ? 'Failed to fetch context: ' . $e->getMessage()
        : 'Failed to fetch context. Please try again later.'
], 500);

// In chat() method
return response()->json([
    'success' => false,
    'error' => config('app.debug') 
        ? 'Failed to process query: ' . $e->getMessage()
        : 'Failed to process query. Please try again later.'
], 500);
```

**Acceptance Criteria:**
- ✅ Production shows generic error messages
- ✅ Debug mode shows detailed error messages
- ✅ No sensitive information exposed in production
- ✅ Errors are still logged for debugging

---

## Phase 2: High Priority Fixes (Fix Soon)

### Task 2.1: Implement Token Usage Tracking
**Priority:** High  
**Severity:** Medium  
**Estimated Time:** 1.5 hours

**Description:**  
Extract token usage data from OpenAI API response instead of always returning 0.

**Files to Modify:**
- `app/Services/OpenAIService.php` - Update `chat()` method to return usage data
- `app/Services/NaturalLanguageService.php` - Extract and return usage from response

**Implementation Steps:**
1. Update `OpenAIService::chat()` to extract usage from response JSON
2. Return usage data alongside content (consider returning array with content and usage)
3. Update `NaturalLanguageService::processChatQuery()` to extract usage from response
4. Update response structure to include actual token counts
5. Update TypeScript interface if needed

**Code Changes:**
```php
// In OpenAIService::chat()
$json = json_decode((string) $resp->getBody(), true);
$content = $json['choices'][0]['message']['content'] ?? '';
$usage = $json['usage'] ?? [];

return [
    'content' => $content,
    'usage' => [
        'prompt_tokens' => $usage['prompt_tokens'] ?? 0,
        'completion_tokens' => $usage['completion_tokens'] ?? 0,
        'total_tokens' => $usage['total_tokens'] ?? 0
    ]
];

// In NaturalLanguageService::processChatQuery()
$response = $this->openAIService->chat($messages, [
    'response_format' => ['type' => 'text']
]);

return [
    'success' => true,
    'reply' => $response['content'],
    'usage' => $response['usage'] ?? [
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0
    ]
];
```

**Acceptance Criteria:**
- ✅ Token usage extracted from OpenAI response
- ✅ Usage data returned in API response
- ✅ Frontend receives accurate token counts
- ✅ Usage tracked for billing/analytics

---

### Task 2.2: Add Input Validation
**Priority:** High  
**Severity:** Medium  
**Estimated Time:** 30 minutes

**Description:**  
Add length limits and array size validation to prevent token overflow and excessive data.

**Files to Modify:**
- `app/Http/Controllers/Api/NaturalLanguageController.php`

**Implementation Steps:**
1. Add max length validation for message content (5000 characters)
2. Add max array size validation for messages array (20 messages)
3. Add validation for assetContext structure
4. Test with extremely long messages

**Code Changes:**
```php
$request->validate([
    'messages' => 'required|array|max:20',
    'messages.*.role' => 'required|in:system,user,assistant',
    'messages.*.content' => 'required|string|max:5000',
    'assetContext' => 'required|array',
    'companyContext' => 'sometimes|array'
]);
```

**Acceptance Criteria:**
- ✅ Messages limited to 20 per request
- ✅ Each message content limited to 5000 characters
- ✅ Validation errors returned properly
- ✅ Frontend handles validation errors gracefully

---

### Task 2.3: Improve Context Error Handling
**Priority:** High  
**Severity:** Medium  
**Estimated Time:** 1 hour

**Description:**  
Disable chat input when context fails to load and show clear error message.

**Files to Modify:**
- `assetGo-frontend/src/app/ai-features/components/natural-language/natural-language.component.ts`
- `assetGo-frontend/src/app/ai-features/components/natural-language/nlq-chat.component.ts`

**Implementation Steps:**
1. Add `hasContext` flag to component state
2. Set flag to false when context loading fails
3. Disable chat input when `hasContext` is false
4. Show clear message that context is required
5. Add retry button or auto-retry mechanism

**Code Changes:**
```typescript
// In natural-language.component.ts
state: NLQState = {
  query: '',
  messages: [],
  isProcessing: false,
  assetContext: null,
  needsApiKey: false,
  hasContext: false // Add this
};

loadContext() {
  this.nlService.getContext()
    .pipe(takeUntil(this.destroy$))
    .subscribe({
      next: (response) => {
        if (response.success && response.data) {
          this.state.assetContext = response.data;
          this.state.hasContext = true;
        } else {
          this.errorMessage = response.error || 'Failed to load context';
          this.state.hasContext = false;
        }
      },
      error: (error) => {
        this.errorMessage = 'Failed to load context. Please try again.';
        this.state.hasContext = false;
      }
    });
}

// Update onMessageSent to check hasContext
onMessageSent(message: string) {
  if (!message.trim() || this.state.isProcessing || this.state.needsApiKey || !this.state.hasContext) {
    return;
  }
  // ... rest of method
}
```

**Acceptance Criteria:**
- ✅ Chat disabled when context is null
- ✅ Clear error message shown to user
- ✅ Retry mechanism available
- ✅ No messages sent with null context

---

### Task 2.4: Remove Unused Import
**Priority:** High  
**Severity:** Low  
**Estimated Time:** 2 minutes

**Description:**  
Remove unused `MaintenanceHistory` import from NaturalLanguageService.

**Files to Modify:**
- `app/Services/NaturalLanguageService.php`

**Implementation Steps:**
1. Remove line 8: `use App\Models\MaintenanceHistory;`
2. Verify no references to MaintenanceHistory exist
3. Run linter to confirm no errors

**Code Changes:**
```php
// Remove this line:
use App\Models\MaintenanceHistory;
```

**Acceptance Criteria:**
- ✅ Unused import removed
- ✅ No compilation errors
- ✅ Code is cleaner

---

## Phase 3: Medium Priority Improvements (Next Sprint)

### Task 3.1: Implement Context Caching
**Priority:** Medium  
**Severity:** Medium  
**Estimated Time:** 2 hours

**Description:**  
Cache context endpoint responses to reduce database queries.

**Files to Modify:**
- `app/Services/NaturalLanguageService.php` - Add caching logic
- `app/Http/Controllers/Api/NaturalLanguageController.php` - Use cached service method
- Consider creating `NaturalLanguageCacheService` if caching logic becomes complex

**Implementation Steps:**
1. Create cache key based on company_id
2. Cache context for 5 minutes (configurable)
3. Clear cache when relevant data changes (assets, work orders updated)
4. Add cache hit/miss logging for monitoring
5. Update frontend service to cache in sessionStorage as well

**Code Changes:**
```php
// In NaturalLanguageService.php
use Illuminate\Support\Facades\Cache;

public function getAssetContext(string $companyId): array
{
    return Cache::remember("nlq-context-{$companyId}", 300, function () use ($companyId) {
        // Existing context building logic
    });
}
```

**Acceptance Criteria:**
- ✅ Context cached for 5 minutes
- ✅ Cache cleared when data changes
- ✅ Frontend also caches in sessionStorage
- ✅ Reduced database queries

---

### Task 3.2: Dynamic Company Name
**Priority:** Medium  
**Severity:** Medium  
**Estimated Time:** 1 hour

**Description:**  
Fetch company name from user profile instead of hardcoding 'Your Company'.

**Files to Modify:**
- `assetGo-frontend/src/app/ai-features/components/natural-language/natural-language.component.ts`
- Consider using existing user/company service

**Implementation Steps:**
1. Check if company name is available in user profile/company service
2. Fetch company name on component init
3. Store in component state
4. Use in chat request with fallback to 'Your Company'
5. Update TypeScript interface if needed

**Code Changes:**
```typescript
// In natural-language.component.ts
companyName: string = 'Your Company';

ngOnInit() {
  this.loadContext();
  this.checkApiKey();
  this.loadCompanyName();
}

loadCompanyName() {
  // Fetch from user service or company service
  // this.userService.getCurrentUser().subscribe(user => {
  //   this.companyName = user.company?.name || 'Your Company';
  // });
}

// In onMessageSent
companyContext: {
  name: this.companyName
}
```

**Acceptance Criteria:**
- ✅ Company name fetched dynamically
- ✅ Fallback to 'Your Company' if not available
- ✅ AI responses use actual company name
- ✅ No hardcoded values

---

### Task 3.3: Improve Error Display
**Priority:** Medium  
**Severity:** Medium  
**Estimated Time:** 1 hour

**Description:**  
Show errors inline instead of full-page overlay to preserve context.

**Files to Modify:**
- `assetGo-frontend/src/app/ai-features/components/natural-language/natural-language.component.ts`
- `assetGo-frontend/src/app/ai-features/components/natural-language/natural-language.component.html` (template)

**Implementation Steps:**
1. Change error display from full-page overlay to banner/alert
2. Position error message at top of page or inline with chat
3. Add dismiss button for errors
4. Keep chat interface visible when error occurs
5. Style error banner appropriately

**Code Changes:**
```html
<!-- Replace full-page error with banner -->
<div *ngIf="errorMessage" class="error-banner">
  <div class="error-content">
    <span>{{ errorMessage }}</span>
    <button (click)="dismissError()">×</button>
  </div>
</div>
```

**Acceptance Criteria:**
- ✅ Errors shown as banner/inline, not full-page
- ✅ Chat interface remains visible
- ✅ Users can dismiss errors
- ✅ Better user experience

---

### Task 3.4: Add Return Type Declarations
**Priority:** Medium  
**Severity:** Low  
**Estimated Time:** 15 minutes

**Description:**  
Add return type declarations to controller methods for better type safety.

**Files to Modify:**
- `app/Http/Controllers/Api/NaturalLanguageController.php`

**Implementation Steps:**
1. Import `JsonResponse` class
2. Add return type to `getContext()` method
3. Add return type to `chat()` method
4. Add return type to `checkApiKey()` method
5. Verify no compilation errors

**Code Changes:**
```php
use Illuminate\Http\JsonResponse;

public function getContext(Request $request): JsonResponse
public function chat(Request $request): JsonResponse
public function checkApiKey(Request $request): JsonResponse
```

**Acceptance Criteria:**
- ✅ All methods have return type declarations
- ✅ No compilation errors
- ✅ Better IDE support and type safety

---

## Phase 4: Low Priority Enhancements (Nice to Have)

### Task 4.1: Configurable Message History Limit
**Priority:** Low  
**Severity:** Low  
**Estimated Time:** 30 minutes

**Description:**  
Make message history limit configurable instead of hardcoded.

**Files to Modify:**
- `app/Services/NaturalLanguageService.php`
- `config/openai.php` (add new config option)

**Implementation Steps:**
1. Add `max_message_history` to `config/openai.php`
2. Use config value instead of hardcoded 10
3. Ensure system message is always kept
4. Document the configuration option

**Code Changes:**
```php
// In config/openai.php
'max_message_history' => (int) env('OPENAI_MAX_MESSAGE_HISTORY', 10),

// In NaturalLanguageService.php
$maxHistory = config('openai.max_message_history', 10);
if (count($messages) > $maxHistory) {
    // Keep system message + recent messages
    $systemMessage = $messages[0];
    $recentMessages = array_slice($messages, -($maxHistory - 1));
    $messages = array_merge([$systemMessage], $recentMessages);
}
```

**Acceptance Criteria:**
- ✅ Message limit configurable via config/env
- ✅ System message always preserved
- ✅ Default value of 10 maintained
- ✅ Documented in config file

---

### Task 4.2: Message Persistence
**Priority:** Low  
**Severity:** Low  
**Estimated Time:** 3 hours

**Description:**  
Store conversation messages in localStorage to persist across page refreshes.

**Files to Modify:**
- `assetGo-frontend/src/app/ai-features/components/natural-language/natural-language.component.ts`
- `assetGo-frontend/src/app/ai-features/shared/natural-language.service.ts` (optional helper methods)

**Implementation Steps:**
1. Save messages to localStorage when updated
2. Load messages from localStorage on component init
3. Add option to clear conversation history
4. Consider syncing with backend for cross-device access (future enhancement)

**Code Changes:**
```typescript
private readonly STORAGE_KEY = 'nlq_conversation';

ngOnInit() {
  this.loadSavedMessages();
  this.loadContext();
  this.checkApiKey();
}

loadSavedMessages() {
  const saved = localStorage.getItem(this.STORAGE_KEY);
  if (saved) {
    try {
      this.state.messages = JSON.parse(saved);
    } catch (e) {
      console.error('Failed to load saved messages', e);
    }
  }
}

onMessageSent(message: string) {
  // ... existing logic ...
  // After adding message:
  this.saveMessages();
}

saveMessages() {
  localStorage.setItem(this.STORAGE_KEY, JSON.stringify(this.state.messages));
}

clearConversation() {
  this.state.messages = [];
  localStorage.removeItem(this.STORAGE_KEY);
}
```

**Acceptance Criteria:**
- ✅ Messages persist across page refreshes
- ✅ Messages load on component init
- ✅ Option to clear conversation
- ✅ Handles corrupted localStorage gracefully

---

### Task 4.3: Streaming Response Support
**Priority:** Low  
**Severity:** Low  
**Estimated Time:** 4 hours

**Description:**  
Implement Server-Sent Events (SSE) for streaming AI responses.

**Files to Modify:**
- `app/Http/Controllers/Api/NaturalLanguageController.php` - Add streaming endpoint
- `app/Services/OpenAIService.php` - Add streaming support
- `assetGo-frontend/src/app/ai-features/shared/natural-language.service.ts` - Add SSE client
- `assetGo-frontend/src/app/ai-features/components/natural-language/nlq-chat.component.ts` - Handle streaming

**Implementation Steps:**
1. Research OpenAI streaming API (if supported)
2. Create streaming endpoint in controller
3. Implement SSE response in Laravel
4. Update frontend to consume SSE stream
5. Update UI to show partial responses as they arrive

**Note:** This is a larger feature that may require OpenAI API streaming support. Consider feasibility first.

**Acceptance Criteria:**
- ✅ Responses stream in real-time
- ✅ Partial responses displayed as they arrive
- ✅ Better UX for longer responses
- ✅ Proper error handling for stream failures

---

### Task 4.4: Message Feedback Mechanism
**Priority:** Low  
**Severity:** Low  
**Estimated Time:** 2 hours

**Description:**  
Add thumbs up/down buttons to assistant messages for feedback.

**Files to Modify:**
- `assetGo-frontend/src/app/ai-features/components/natural-language/nlq-chat.component.ts` - Add feedback UI
- `assetGo-frontend/src/app/ai-features/shared/natural-language.service.ts` - Add feedback API call
- `app/Http/Controllers/Api/NaturalLanguageController.php` - Add feedback endpoint
- Create `nlq_feedback` database table (migration)

**Implementation Steps:**
1. Design feedback UI (thumbs up/down buttons)
2. Create database migration for feedback table
3. Add feedback endpoint in backend
4. Store feedback with message ID, user ID, company ID
5. Use feedback to improve prompts (future enhancement)

**Code Changes:**
```typescript
// In nlq-chat.component.ts template
<div class="message-feedback" *ngIf="message.type === 'assistant'">
  <button (click)="submitFeedback(message, 'positive')">👍</button>
  <button (click)="submitFeedback(message, 'negative')">👎</button>
</div>
```

**Acceptance Criteria:**
- ✅ Feedback buttons visible on assistant messages
- ✅ Feedback stored in database
- ✅ Feedback tracked per user/company
- ✅ Can be used for analytics/improvement

---

## Implementation Order

### Week 1 (Critical)
1. Task 1.1: Fix XSS Vulnerability
2. Task 1.2: Fix Error Message Exposure

### Week 2 (High Priority)
3. Task 2.1: Implement Token Usage Tracking
4. Task 2.2: Add Input Validation
5. Task 2.3: Improve Context Error Handling
6. Task 2.4: Remove Unused Import

### Week 3 (Medium Priority)
7. Task 3.1: Implement Context Caching
8. Task 3.2: Dynamic Company Name
9. Task 3.3: Improve Error Display
10. Task 3.4: Add Return Type Declarations

### Week 4+ (Low Priority - Backlog)
11. Task 4.1: Configurable Message History
12. Task 4.2: Message Persistence
13. Task 4.3: Streaming Response Support
14. Task 4.4: Message Feedback Mechanism

---

## Testing Checklist

### Security Testing
- [ ] Test XSS protection with malicious HTML/JavaScript in AI responses
- [ ] Verify error messages don't expose sensitive data in production
- [ ] Test input validation with extremely long messages (>5000 chars)
- [ ] Test input validation with >20 messages in array

### Functionality Testing
- [ ] Test context loading failure scenarios
- [ ] Test API key check failures
- [ ] Test conversation with >10 messages (history truncation)
- [ ] Test token usage tracking accuracy
- [ ] Test error display and dismissal

### Performance Testing
- [ ] Test context endpoint with caching enabled
- [ ] Test multiple concurrent chat requests
- [ ] Verify rate limiting works correctly
- [ ] Test localStorage message persistence

### User Experience Testing
- [ ] Test error states and recovery
- [ ] Test conversation persistence across refreshes
- [ ] Test markdown rendering with various content types
- [ ] Test dynamic company name display

---

## Estimated Total Time

- **Phase 1 (Critical):** 1.5 hours
- **Phase 2 (High Priority):** 3.5 hours
- **Phase 3 (Medium Priority):** 4.25 hours
- **Phase 4 (Low Priority):** 9.5 hours

**Total:** ~18.75 hours (approximately 2.5 days of focused work)

---

## Notes

1. **Critical issues must be fixed before production deployment**
2. **High priority issues should be addressed in next sprint**
3. **Medium priority issues can be planned for future sprints**
4. **Low priority issues are enhancements, not blockers**

5. **Dependencies:**
   - Task 2.1 requires updating OpenAIService first
   - Task 3.1 should be done before Task 4.2 (for better performance)
   - Task 4.3 (streaming) may require OpenAI API streaming support - verify feasibility first

6. **Consider creating a shared cache service** if multiple AI features need caching (predictive maintenance, NLQ, etc.)

7. **Review rate limits** after implementing caching to ensure they're still appropriate

