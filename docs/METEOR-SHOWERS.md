# Meteor-shower website contract

The guest routes `/meteor-showers` and `/meteor-showers/{code}` read the
existing REST endpoints from `solar-system-db`. Source: IAU Meteor Data Center
(MDC). They do not calculate viewing conditions or meteor rates.

## List

`GET /api/v1/meteor-showers` accepts `established_only`, `active_on` and `limit`.
The response is `{ "items": [...], "count": <returned rows> }`, not the
`results` envelope used by most other catalogue endpoints. Each row is a
parameter set (observation campaign), not a distinct shower. The website groups
rows by `iau_no`, preserving every returned set. It uses a fixed limit of 1000,
the backend maximum. There is no offset or total. At the limit, both shower
coverage and the final group can be incomplete; the website explicitly warns
of this uncertainty. A displayed count describes only returned data.

The website's shareable query parameters are `active_on=YYYY-MM-DD` and
`established_only=1` (or `0`). Dates must be exact, valid Gregorian calendar
dates in years 0001–9999; invalid dates never reach the meteor endpoint.
Established-only means MDC status codes 1 or 6, enforced by the backend.
The date filter means the reported peak solar longitude is within 15 degrees
(circular distance) of the approximate solar longitude for that date. It does
not imply visibility, exact seasonal boundaries or an hourly meteor rate.
Sets without a reported peak cannot match that date filter.

A 404/failed request or unexpected envelope is shown as unavailable. An empty
`items` list can mean either no matches or an absent meteor-showers table: the
backend returns the same response for both. The empty state explains this
limitation without claiming the catalogue is complete or loaded.

## Detail

`GET /api/v1/meteor-showers/{code}` accepts a shower code or name and returns
`iau_no`, `code`, `name`, `status_label`, `parameter_sets` and `parent`.
The detail view renders every `parameter_sets` entry, independent of filters
on the list. Each set retains its measurements, status, activity, observing
technique, member count, submission date, source reference and proposed parent.
Missing values remain explicit; source strings are escaped, not interpreted
as HTML. Units are shown alongside measurements. Numeric display uses up to
six decimal places; use the REST response or published database for source
precision and scientific reuse.

The backend's top-level `parent` is selected from one parameter set. It cannot
represent all campaigns. Website parent links therefore use each set's own
`parent_object_id` and `parent_body`; text-only proposed parents remain text.

A detail 404 cannot distinguish an unknown shower, an absent table or an older
backend without this route. The website returns an explanatory page with
`noindex` rather than asserting which happened. A successful record containing
no parameter sets is separately labelled as such. A temporary request failure
uses the standard catalogue-unavailable panel.

The real REST envelopes and field names are represented in
`tests/Feature/MeteorShowersTest.php` using test-local payloads. Tests exercise
IAU identity grouping, URL filters, invalid/leap dates, cap uncertainty, all
parameter sets, conflicting parent associations, escaped references and
empty/older/unavailable backend responses without live network access.
