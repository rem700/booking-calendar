# Shootbook

Formerly Booking Calendar.

A WordPress plugin for booking requests with a live calendar. Visitors pick a service, a place and a free start time, leave their contacts and send a request. You confirm or decline it in WP admin, and the client gets an email in their language.

Free times are worked out from your working hours, the time you need to prepare, the requests already received and any number of Google Calendar (iCal) feeds.

## Features

- Any services, locations and options, all set in **Requests → Settings**, no code needed
- Working hours per weekday, with separate hours for each location
- Locations booked separately if you like, so two halls can take the same hour; each location can take several bookings at once
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
- Prices: a price per hour for each service and extra prices for options, shown in the form and saved with the request. A site script can still set its own price (see below)
- Packages: a set number of hours at their own price (for example 5 or 10 hours), offered in the form next to the durations and limited to the locations you choose. The client books all the hours in one go; options are added to the package price

## Clients (CRM)

**Requests → Clients** keeps one card per client, made automatically from the requests:

- stages: new request, replied, confirmed, prepaid, done, photos delivered, review asked, lost (with a reason)
- history: every request, answers, stage changes, your notes, messages and tasks with due dates
- source: where the visitor first came from (UTM tags or the referring site: Instagram, Google…)
- requests from Instagram, phone or in person: **Clients → Add a request from Instagram or by hand** (Instagram handle, contacts, date; can hold the time in the calendar)
- **Requests → Report**: requests and confirmations by source, clients by stage, why requests are lost
- a morning e-mail summary: new requests, requests waiting for an answer, sessions today and tomorrow, tasks, clients to follow up
- the same summary as JSON at `GET /wp-json/bk-booking/v1/crm/digest` (admin login or an application password)

The history is kept in its own tables, so it stays after old requests are removed.

## Instagram Direct

Requests → Instagram connects a professional Instagram account (Business or Creator) through the Instagram API with Instagram Login. You make an app on developers.facebook.com, paste its Instagram app secret and an access token, and give Meta the webhook address and verify token shown on the page.

- A person who writes for the first time becomes a client with the source Instagram. If a client with the same @name is already in the list, the message goes to that card.
- Every message, in both directions, goes to the client's history. Answers written in the Instagram app are saved too.
- New messages come by webhook (`POST /wp-json/bk-booking/v1/instagram`, signed with the app secret). Every hour the site also reads recent conversations, so nothing is lost while the site was down.
- You can answer from the client card or send a ready-made reply to Direct within 24 hours after the client's last message, as Instagram allows.
- The token is renewed automatically. It and the app secret are kept in the `bkb_instagram` option and never go into the settings export.

## Ready-made replies

Under every request and in each client card there are message templates (time is free, booking confirmed, time taken, ask for details, follow-up, thanks and review) already filled with the date, service, place and price. They are shown in the client's language with the admin's language underneath as a translation; one click copies the text, opens the Instagram chat, or sends it by e-mail from the site (the e-mail is saved in the client's history). The sender address is set in **Settings → General → Sender address**. Edit or add templates in **Settings → Reply templates**. A line whose placeholder has no value is left out.

## Installation

1. Download this repository as a ZIP and install it in **Plugins → Add New → Upload Plugin**, or put the folder in `wp-content/plugins/`.
2. Activate **Shootbook**.
3. Open **Requests → Settings** and fill in the tabs: General, Schedule, Services, Packages, Locations, Options, Form fields, Texts, Reply templates, Appearance.
4. Put the shortcode on a page:

```
[booking_calendar]
```

`[bk_booking]` works as well. To show the form in a fixed language: `[booking_calendar lang="de"]`.

The admin follows the language in your WordPress profile. English and Russian are included, the translation files are in `languages/`.

## Prefilling the form

The form reads its starting choice from the page address:

```
/book/?service=Standard&location=studio&hours=2&video=1
```

`service`, `location`, `hours`, `pack` (a package id) and any option id. Another plugin or theme can set `window.BKB_PREFILL` to the same kind of query string before the form loads.

## Extending

### PHP

| Hook | Type | What it does |
|---|---|---|
| `bkb_front_config` | filter | Change the settings sent to the form. Args: `$config, $lang` |
| `bkb_busy` | filter | Add or remove busy intervals. Args: `$busy, $from, $to` |
| `bkb_price` | filter | Change the price worked out from the settings, or return `null` for no price. Args: `$amount, $service, $hours, $opts, $pack` (`$pack` is the package id or empty) |
| `bkb_request_data` | filter | Change a request before it is saved. Args: `$data, $params` |
| `bkb_summary_lines` | filter | Change the summary lines used in emails and admin. Args: `$lines, $booking, $lang, $with_contact` |
| `bkb_request_saved` | action | Runs after a request is saved. Args: `$id, $booking` |
| `bkb_status_changed` | action | Runs when you confirm or decline. Args: `$id, $status` |
| `bkb_enqueue` | action | Runs when the form assets load. Use it to add your own script. Args: `$lang` |

### JavaScript

The form exposes `window.bkb`:

```js
bkb.state            // current choice: service, location, hours, pack (package id or ''), opts, date, time…
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

- `GET /wp-json/bk-booking/v1/slots?month=2026-12&h=2&loc=studio`: free start times for a month; add `&pack=<id>` for a package
- `POST /wp-json/bk-booking/v1/request`: send a request (used by the form)

## Requirements

WordPress 6.0 or newer, PHP 7.4 or newer.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
