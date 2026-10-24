# Hotel booking: dashboard review and redesign

## How to apply

Copy the files in this folder over the same paths in your project, then run `npm run build` (or `npm run dev`).
No migrations, no new packages, no new routes.

| File | Change |
|---|---|
| `app/Services/DashboardStats.php` | Adds today's arrival/departure lists, dirty-room count, previous-period figures, semantic colors |
| `app/Http/Controllers/DashboardController.php` | Passes the guest's current/next stay to the view |
| `resources/views/dashboard.blade.php` | Rebuilt page (staff and guest) |
| `resources/views/components/dashboard-layout.blade.php` | Brand logo, calmer nav, distinct icons, compact account block, skip link |
| `resources/views/components/dashboard/*.blade.php` | Redesigned `stat-card`, `panel`, `bar-chart`, `line-chart`, `donut`, `h-bars`; new `button`, `delta`, `worklist` |
| `resources/css/app.css` | Adds the `[x-cloak]` rule (chart tooltips need it) |
| `new-translation-strings.txt` | 39 new strings to translate for `am` and `om` (they fall back to English until then) |

## What I found

**UX (these matter more than looks)**

1. **The page didn't answer "what do I need to do now?"** "Arrivals waiting 0 / Leaving today 1" were just numbers, and you had to open Front desk to act. The Today section now lists who is arriving and leaving, with Check in / Check out buttons, an "Overdue" flag, the balance owed, and a "Room not ready" warning when an arriving guest's room isn't clean.
2. **The 7/30/90-day switch sat in the page header, so it looked like it controlled everything.** It only affects the period section. It now sits next to that section's heading.
3. **Numbers had no context.** "ETB 75,600" says nothing without a comparison. Every period figure now shows the change against the previous period of the same length. Cancellations are colored the other way round (up is bad).
4. **Charts were hard to read.** One unlabeled number at the top, two dates at the bottom, tooltips only through the browser's slow `title`. They now have a labeled axis with tidy gridlines, 3-5 dates, instant tooltips, and an empty-state message instead of a blank box. Screen readers get a data table.
5. **Quick actions were at the bottom**, below 7 charts, and duplicated the sidebar. "New booking" (the one action people actually repeat) moved to the header; the rest live in the sidebar.
6. **The guest dashboard was two buttons on an empty page.** It now shows the current or next stay with dates, nights, status and reference, or a clear empty state with a "Book a room" action.

**UI**

7. **No brand identity.** The public site is coral; the dashboard was default Breeze (indigo, grey, the Laravel logo). It now uses your house icon and coral as the single accent, used sparingly: the primary button, the "needs action" dots, and the money chart.
8. **Everything was the same size.** Four identical cards, then five, then seven. Now there's a hierarchy: Today (worklists) first, key figures second, charts third. The Revenue and Occupancy charts get two thirds of the width.
9. **All-caps, wide-tracked labels and buttons** (`uppercase tracking-widest`) are hard to read, especially for Amharic and Oromo text. Replaced with sentence case.
10. **Sidebar:** six menu items shared one icon; the account block took four lines and squeezed "Reports & tax" off-screen. Each item has its own icon, and the account block is one row.
11. **Colors had no meaning** (indigo, emerald, amber chosen per chart). Now: green = good/in house, blue = waiting, amber = needs cleaning, red = cancelled/overdue, grey = done.

## Things to know before you rely on it

- **I couldn't render this.** My sandbox has no Laravel install or browser. I linted the PHP, converted the Blade to PHP to check syntax, and ran the chart math with your real numbers (the ETB 66,600 day, 3 guests, empty data), but I haven't seen it on a screen. Check the Today section and the two charts first.
- The primary button uses `brand-dark` (#e31c5f), not `brand` (#ff385c), because white text on #ff385c is only about 3.6:1 contrast; the darker red passes AA at 4.6:1. Your public "Book now" button has the same issue.
- The dashboard now runs about 6 more queries (previous-period counts, today's lists). Fine at your size.

## Bugs and risks I noticed in the code (not changed)

- **Timezone mismatch.** `DashboardStats` and `FrontDeskController` use `today()` (the app timezone), but `PropertyTime` says a hotel day follows `payments.timezone`. If those differ, "today" can be wrong for a few hours each day. I used `PropertyTime` only for the greeting/date.
- **Everything is grouped in PHP.** The occupancy loop is days x stays and loads whole result sets into memory. It's fine for 9 rooms; at a few thousand bookings, move it to SQL or cache the result.
- **`x-link-button`** is still the uppercase Breeze style used across the app. I added `x-dashboard.button` instead of changing it globally. Switching the other pages over would make the whole staff area consistent.

## Public site (from your first two screenshots)

- The room cards and hero show placeholder house icons instead of photos. For a booking site, this is the biggest conversion issue; real photos would change how it feels more than any CSS.
- The search button is icon-only. Add a visible "Search" label or at least an `aria-label`.
- All three "Book" buttons look identical, so "Popular" isn't backed up visually. Make the Deluxe button the filled coral one.
