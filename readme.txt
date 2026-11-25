=== Shootbook ===
Contributors: rem700
Tags: booking, appointments, calendar, photo studio, crm
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Booking requests with a live calendar and a simple client list, made for photo studios, photographers and hourly rentals.

== Description ==

Visitors pick a service, a location and a free start time, leave their contacts and send a request. You confirm or decline it in WP admin, and the client gets an e-mail in their language.

Free times are worked out from your opening hours, the time you need to prepare, the requests already received and any number of iCal feeds, such as Google Calendar.

= Booking form =

* Any services, locations and options, set in Requests → Settings, no code needed
* Opening hours per weekday, with separate hours for each location
* Session lengths, preparation time, start time step, how many days ahead and how soon a booking can start
* Busy times from one or more iCal feeds (Google Calendar, Outlook, Apple)
* Form fields you can switch on or off, a field for extra details, a session language choice
* Form texts in English, German, Hungarian, Russian and Ukrainian, each one editable
* Colours and fonts to match your theme
* Works with Polylang and WPML: the form and the e-mails follow the page language
* E-mails: receipt to the client, confirmation, decline, reminder the day before, notice to you
* "Add to Google Calendar" link for you and for the client
* Old requests are removed after the number of days you choose
* Settings export and import as JSON

= Clients =

Requests → Clients keeps one card per client, made from the requests:

* stages from a new request to a delivered shoot and a review, or lost with a reason
* history: every request, reply, stage change, note, message and task with a due date
* source: where the visitor first came from (UTM tags or the referring site)
* requests that came by Instagram, phone or in person can be added by hand
* a report of requests and confirmations by source, clients by stage and reasons for lost requests
* a morning e-mail with new requests, requests waiting for a reply, today's and tomorrow's sessions and tasks

= Reply templates =

Under every request and in each client card there are message templates already filled with the date, service, location and price. They are shown in the client's language with a translation into yours. One click copies the text, opens the Instagram chat or sends it by e-mail from the site.

= For developers =

The plugin has PHP filters and actions, a small JavaScript API for showing a price in the form, and a REST API for free times. See the README on GitHub: https://github.com/rem700/booking-calendar

== Installation ==

1. Install the plugin from Plugins → Add New, or upload the folder to `wp-content/plugins/`.
2. Activate it.
3. Open Requests → Settings and fill in the tabs.
4. Put the shortcode `[booking_calendar]` on a page. To show the form in a fixed language, use `[booking_calendar lang="de"]`.

== Frequently Asked Questions ==

= What language is the admin in? =

The admin follows the language in your WordPress profile. English and Russian are included.

= Can it show a price? =

Yes, with a few lines of JavaScript that set the price from the chosen service and length. The README on GitHub has an example.

= Can two halls be booked at the same time? =

Not yet. For now a request blocks its time in all locations.

== External services ==

The plugin connects to outside services only in these cases.

* iCal feeds. If you add iCal addresses in Settings → General, the site downloads those feeds to find busy times. Only a plain request for the feed is sent, without any visitor data. The feed belongs to the calendar service you use, for example Google Calendar: https://policies.google.com/terms and https://policies.google.com/privacy
* Google Calendar links. Request pages and e-mails contain an "Add to Google Calendar" link. Nothing is sent until someone opens it.
* Instagram links. A client card can contain a link to the client's Instagram profile or chat. Nothing is sent until you open it.

== Changelog ==

= Unreleased =

* The admin is in English with a Russian translation and follows the profile language.
* Own request sources can be added in the settings.
* Two requests for the same time sent at the same moment can no longer both be saved.
* The calendar works on sites without pretty permalinks.
* Deactivating stops the scheduled tasks, and deleting the plugin removes its cached feeds.
