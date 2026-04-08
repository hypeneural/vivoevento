# Task 16.2 Visual Verification Checklist

## Testing Instructions

### 1. Start Development Server
```bash
cd apps/landing
npm run dev
```

### 2. Visual Verification Checklist

#### Desktop (>768px)
- [ ] Scroll to 80% of page
- [ ] Floating CTA appears in bottom-right corner
- [ ] CTA has glassmorphism effect (backdrop blur)
- [ ] CTA has subtle glow effect
- [ ] Close button is visible in top-right of card
- [ ] Primary CTA (Agendar demonstração) has warm gradient
- [ ] Secondary CTA (WhatsApp) has cyan accent
- [ ] Hover states work on all buttons
- [ ] Click close button - CTA disappears
- [ ] Refresh page - CTA appears again after 80% scroll
- [ ] Close CTA and refresh - CTA should appear again (sessionStorage)
- [ ] Close CTA in same session - should NOT appear again

#### Mobile (<768px)
- [ ] Scroll to 80% of page
- [ ] Floating CTA appears at bottom (full width)
- [ ] CTA has 1rem margins on left/right
- [ ] All buttons have minimum 48px height
- [ ] Touch targets are easy to tap
- [ ] Close button is easy to tap
- [ ] CTAs are stacked vertically
- [ ] Text is readable at mobile size

#### Accessibility
- [ ] Tab through page - CTA is reachable via keyboard
- [ ] Tab order is logical (close → primary → secondary)
- [ ] Focus indicators are visible
- [ ] Press Escape key - CTA should close (if implemented)
- [ ] Screen reader announces "Ações rápidas de conversão"
- [ ] All buttons have descriptive labels

#### Animations
- [ ] CTA entrance is smooth (fade + slide up)
- [ ] CTA exit is smooth when closed
- [ ] Hover animations are subtle
- [ ] No janky animations or layout shifts

#### Functionality
- [ ] Primary CTA link includes UTM params (if present in URL)
- [ ] Secondary CTA opens WhatsApp in new tab
- [ ] Close button dismisses CTA
- [ ] Dismissal persists in current session
- [ ] New session shows CTA again
- [ ] Analytics events fire on interactions (check console)

#### Edge Cases
- [ ] Works with very short pages (CTA appears immediately)
- [ ] Works with very long pages (CTA appears at 80%)
- [ ] Works when scrolling up and down repeatedly
- [ ] Works when resizing browser window
- [ ] Works with different zoom levels (90%, 110%, 125%)

#### Browser Compatibility
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

### 3. Performance Verification

#### Lighthouse Audit
```bash
npm run build
npm run preview
# Run Lighthouse audit in Chrome DevTools
```

**Expected Scores:**
- Performance: ≥85
- Accessibility: ≥90
- Best Practices: ≥90
- SEO: ≥90

#### Core Web Vitals
- [ ] LCP (Largest Contentful Paint): ≤2.5s
- [ ] INP (Interaction to Next Paint): ≤200ms
- [ ] CLS (Cumulative Layout Shift): ≤0.1

### 4. Analytics Verification

#### Open Browser Console
```javascript
// Check if gtag is available
console.log(typeof window.gtag);

// Monitor events
window.gtag = new Proxy(window.gtag || function(){}, {
  apply(target, thisArg, args) {
    console.log('Analytics Event:', args);
    return target.apply(thisArg, args);
  }
});
```

**Expected Events:**
1. On Primary CTA click:
   ```javascript
   {
     event: 'click',
     event_category: 'CTA',
     event_label: 'Floating CTA - Primary',
     value: 1
   }
   ```

2. On Secondary CTA click:
   ```javascript
   {
     event: 'click',
     event_category: 'CTA',
     event_label: 'Floating CTA - Secondary',
     value: 1
   }
   ```

3. On Close button click:
   ```javascript
   {
     event: 'floating_cta_interaction',
     event_category: 'Engagement',
     event_label: 'Floating CTA - close',
     value: 0
   }
   ```

### 5. Reduced Motion Testing

#### Enable Reduced Motion
**Windows:**
Settings → Accessibility → Visual effects → Animation effects (OFF)

**macOS:**
System Preferences → Accessibility → Display → Reduce motion (ON)

**Browser DevTools:**
Chrome: DevTools → Rendering → Emulate CSS media feature prefers-reduced-motion

**Expected Behavior:**
- [ ] CTA still appears/disappears
- [ ] No slide/scale animations
- [ ] Only opacity transitions
- [ ] Hover states don't transform elements

### 6. SessionStorage Testing

#### Test Dismissal Persistence
```javascript
// Open Console
sessionStorage.getItem('ev_floating_cta_dismissed'); // Should be null initially

// Close CTA
// Check again
sessionStorage.getItem('ev_floating_cta_dismissed'); // Should be 'true'

// Scroll up and down - CTA should NOT appear

// Clear sessionStorage
sessionStorage.removeItem('ev_floating_cta_dismissed');

// Scroll to 80% - CTA should appear again
```

### 7. UTM Parameter Testing

#### Test URL with UTM Params
```
http://localhost:5173/?utm_source=google&utm_medium=cpc&utm_campaign=test
```

**Verify:**
- [ ] Scroll to 80%
- [ ] Click Primary CTA
- [ ] Check href attribute includes UTM params
- [ ] Click Secondary CTA
- [ ] Check href attribute includes UTM params

### 8. Visual Regression Testing (Optional)

#### Take Screenshots
1. Desktop - CTA visible
2. Desktop - CTA hover state
3. Mobile - CTA visible
4. Mobile - CTA hover state

**Compare with design mockups or previous screenshots**

## Known Issues / Limitations

### Current Limitations:
1. **Escape Key:** Not implemented (could be added as enhancement)
2. **Scroll Up Behavior:** CTA doesn't hide when scrolling back up (by design)
3. **Multiple Sessions:** Dismissal only persists in current session (by design)

### Browser-Specific Notes:
1. **Safari:** Backdrop blur may have slight performance impact
2. **Firefox:** Backdrop blur requires `layout.css.backdrop-filter.enabled` flag
3. **IE11:** Not supported (uses modern CSS features)

## Troubleshooting

### CTA Not Appearing
1. Check if `ScrollUIContext` is properly wrapping the app
2. Check if `showFloatingCTA` state is updating (React DevTools)
3. Check if sessionStorage has dismissal flag set
4. Check browser console for errors

### Styling Issues
1. Check if SCSS modules are compiling correctly
2. Check if CSS variables are defined in `_variables.scss`
3. Check if mixins are imported correctly
4. Clear browser cache and rebuild

### Analytics Not Firing
1. Check if `window.gtag` is defined
2. Check if analytics script is loaded
3. Check browser console for errors
4. Verify event payload structure

## Sign-Off Checklist

Before marking task as complete:
- [ ] All visual verification items checked
- [ ] All accessibility items checked
- [ ] All functionality items checked
- [ ] Performance metrics meet targets
- [ ] Analytics events verified
- [ ] Mobile testing complete
- [ ] Browser compatibility verified
- [ ] No console errors
- [ ] Build successful
- [ ] Tests passing

## Notes

Add any observations or issues found during testing:

---

**Tested by:** _________________
**Date:** _________________
**Browser/Device:** _________________
**Issues Found:** _________________
