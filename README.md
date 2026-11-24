# Booking Calendar

A WordPress plugin for booking requests with a live calendar. Visitors pick a service, a place and a free start time, leave their contacts and send a request. You confirm or decline it in WP admin, and the client gets an email in their language.

Free times are worked out from your working hours, the time you need to prepare, the requests already received and any number of Google Calendar (iCal) feeds.

## Features

- Any services, locations and options, all set in **Заявки → Настройки** (Requests → Settings), no code needed
- Working hours per weekday, with separate hours for each location
- Session lengths, preparation time, start time step, how many days ahead and how soon a booking can start
- Busy times from one or more iCal feeds (Google Calendar, Outlook, Apple)
- Form fields you can switch on or off, a field for extra details, a session language choice
- Texts in English, German, Hungarian, Russian and Ukrainian, each one editable
- Colours and fonts to match your theme
- Works with Polylang and WPML: the form and the emails follow the page language
- Emails: receipt to the client, confirmation, decline, reminder the day before, notice to you (to one or several addresses)
- "Add to Google Calendar" link for you and for the client
- Old requests are removed after the number of days you choose
- Settings export and import as JSON, so the same setup moves to another site in one step
- No price logic inside: add your own with a few lines of JavaScript (see below)

## Clients (CRM)

**Заявки → Клиенты** keeps one card per client, made automatically from the requests:

- stages: new request, replied, confirmed, prepaid, done, photos delivered, review asked, lost (with a reason)
- history: every request, answers, stage changes, your notes, messages and tasks with due dates
- source: where the visitor first came from (UTM tags or the referring site: Instagram, Google…)
- requests from Instagram, phone or in person: **Клиенты → Добавить заявку** (Instagram handle, contacts, date; can hold the time in the calendar)
- **Заявки → Отчёт**: requests and confirmations by source, clients by stage, why requests are lost
- a morning e-mail summary: new requests, requests waiting for an answer, sessions today and tomorrow, tasks, clients to follow up
- the same summary as JSON at `GET /wp-json/bk-booking/v1/crm/digest` (admin login or an application password)

The history is kept in its own tables, so it stays after old requests are removed.

## Ready-made replies

Under every request and in each client card there are message templates (time is free, booking confirmed, time taken, ask for details, follow-up, thanks and review) already filled with the date, service, place and price. They are shown in the client's language with the admin's language underneath as a translation; one click copies the text, opens the Instagram chat or an e-mail. Edit or add templates in **Настройки → Готовые ответы**. A line whose placeholder has no value is left out.

## Installation

1. Download this repository as a ZIP and install it in **Plugins → Add New → Upload Plugin**, or put the folder in `wp-content/plugins/`.
2. Activate **Booking Calendar**.
3. Open **Заявки → Настройки** and fill in the tabs: General, Schedule, Services, Locations, Options, Form fields, Texts, Style.
4. Put the shortcode on a page:

```
[booking_calendar]
```

`[bk_booking]` works as well. To show the form in a fixed language: `[booking_calendar lang="de"]`.

The admin screens are in Russian for now.

## Prefilling the form

The form reads its starting choice from the page address:

```
/book/?service=Standard&location=studio&hours=2&video=1
```

`service`, `location`, `hours` and any option id. Another plugin or theme can set `window.BKB_PREFILL` to the same kind of query string before the form loads.

## Extending

### PHP

| Hook | Type | What it does |
|---|---|---|
| `bkb_front_config` | filter | Change the settings sent to the form. Args: `$config, $lang` |
| `bkb_busy` | filter | Add or remove busy intervals. Args: `$busy, $from, $to` |
| `bkb_request_data` | filter | Change a request before it is saved. Args: `$data, $params` |
| `bkb_summary_lines` | filter | Change the summary lines used in emails and admin. Args: `$lines, $booking, $lang, $with_contact` |
| `bkb_request_saved` | action | Runs after a request is saved. Args: `$id, $booking` |
| `bkb_status_changed` | action | Runs when you confirm or decline. Args: `$id, $status` |
| `bkb_enqueue` | action | Runs when the form assets load. Use it to add your own script. Args: `$lang` |

### JavaScript

The form exposes `window.bkb`:

```js
bkb.state            // current choice: service, location, hours, opts, date, time…
bkb.config           // settings for this page
bkb.setPrice(fn)     // fn(state) returns the price text, or '' to hide the price block
bkb.refresh()        // redraw after you change state
```

Events on `document`: `bkb:change` and `bkb:submitted`, with `detail.state`.

Example of a simple price:

```js
document.addEventListener('DOMContentLoaded', function () {
  if (!window.bkb) return;
  bkb.setPrice(function (s) {
    var perHour = { 'Standard': 50, 'Premium': 80 }[s.service];
    return perHour ? '€' + perHour * s.hours : '';
  });
});
```

### REST API

- `GET /wp-json/bk-booking/v1/slots?month=2026-12&h=2&loc=studio`: free start times for a month
- `POST /wp-json/bk-booking/v1/request`: send a request (used by the form)

## Requirements

WordPress 6.0 or newer, PHP 7.4 or newer.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
