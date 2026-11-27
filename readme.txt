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
* Several halls booked at the same time, each with its own capacity
* Session lengths, preparation time, start time step, how many days ahead and how soon a booking can start
* Busy times from one or more iCal feeds (Google Calendar, Outlook, Apple)
* Form fields you can switch on or off, a field for extra details, a session language choice
* Form texts in English, German, Hungarian, Russian and Ukrainian, each one editable
* Prices per hour for services and extra prices for options
* Colours and fonts to match your theme
* Works with Polylang and WPML: the form and the e-mails follow the page language
* E-mails: receipt to the client, confirmation, decline, reminder the day before, notice to you
* Google Calendar sync: confirmed bookings go to your calendar, move with a new time and disappear when declined; the calendar's events are busy time on the site at once
* A private calendar address with the confirmed bookings for Apple Calendar, Outlook or a phone
* A calendar file in the client's confirmation e-mail, and "Add to Google Calendar" link
* Old requests are removed after the number of days you choose
* Settings export and import as JSON
* The form on a site made in Framer, Webflow, Tilda or Wix: copy the code from Requests → Site widget

= Clients =

Requests → Clients keeps one card per client, made from the requests:

* stages from a new request to a delivered shoot and a review, or lost with a reason
* history: every request, reply, stage change, note, message and task with a due date
* source: where the visitor first came from (UTM tags or the referring site)
* requests that came by Instagram, phone or in person can be added by hand
* a report of requests and confirmations by source, clients by stage and reasons for lost requests
* a morning e-mail with new requests, requests waiting for a reply, today's and tomorrow's sessions and tasks
* Instagram Direct: a person who writes to your Instagram becomes a client, every message goes to the history, and you can answer from the card

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

Yes. Set a price per hour for a service and extra prices for options in the settings. The form shows the price, and the same price is worked out again on the server and saved with the request. For other rules, a few lines of JavaScript can set the price instead. The README on GitHub has an example.

= Can two halls be booked at the same time? =

Yes. Turn on "Locations are booked separately" on the Locations tab. Then a request takes up time only in its own location, and each location can take several bookings at once if you set that.

== External services ==

The plugin connects to outside services only in these cases.

* iCal feeds. If you add iCal addresses in Settings → General, the site downloads those feeds to find busy times. Only a plain request for the feed is sent, without any visitor data. The feed belongs to the calendar service you use, for example Google Calendar: https://policies.google.com/terms and https://policies.google.com/privacy
* Google Calendar API. Only if you connect a Google account in Requests → Google Calendar. The site then sends the confirmed bookings (and, if you tick it, the requests waiting for an answer) to the chosen calendar: the time, the service, the location, the client's name, contacts and message, and a link to the request. It also reads the times of the calendar's events to find busy time. The keys stay on your site. Service by Google: https://policies.google.com/terms and https://policies.google.com/privacy
* Google Calendar links. Request pages and e-mails contain an "Add to Google Calendar" link. Nothing is sent until someone opens it.
* Instagram API. Only if you connect your Instagram account in Requests → Instagram. Instagram then sends new Direct messages to your site, and the site asks Instagram every hour for recent conversations and for the name of a person who wrote. When you answer from a client card, the text goes to Instagram. The access token and app secret stay on your site. Service by Meta: https://developers.facebook.com/terms/ and https://privacycenter.instagram.com/policy
* Instagram links. A client card can contain a link to the client's Instagram profile or chat. Nothing is sent until you open it.

== Changelog ==

= Unreleased =

* The admin is in English with a Russian translation and follows the profile language.
* Own request sources can be added in the settings.
* Locations can be booked separately, each with its own capacity.
* Prices per hour for services and extra prices for options.
* Two requests for the same time sent at the same moment can no longer both be saved.
* The calendar works on sites without pretty permalinks.
* Deactivating stops the scheduled tasks, and deleting the plugin removes its cached feeds.
* Messages from Instagram Direct come into the client list, and you can answer them from the client card.
* Packages: a set number of hours at their own price, chosen in the form next to the durations.
* Google Calendar sync through the Google Calendar API: bookings go to the calendar, move and are removed by themselves; busy time is read from the calendar at once.
* A request can be moved to another time from its page.
* A private calendar address with the confirmed bookings, and a calendar file in the client's confirmation e-mail.
* The form on sites made in Framer, Webflow, Tilda or Wix, with a code to copy, a preview and a list of the sites that may show it.
