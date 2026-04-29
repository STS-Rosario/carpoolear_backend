# Mutation Notes (Session Log)

This file tracks mutants killed during the current hardening session, with the root cause and the test/fix that killed each group.

## FriendsRepository

- `56370d7f8538bd76` (`Line 17: EqualToIdentical`)
  - Cause: test coverage only passed integer accepted state; strict identity mutation (`==` -> `===`) survived.
  - Fix: added `test_add_accepts_numeric_string_friend_state_from_request_payloads`.

- `a98a28c1baf267eb`, `008590db7b9f84cf` (`Line 41: Concat*`)
  - Cause: search tests did not assert middle-substring wildcard behavior.
  - Fix: added `test_get_search_matches_substring_in_middle_of_name`.

- `1592288398f6cf6e` (`Line 53: RemoveMethodCall`)
  - Cause: `getPending` lacked a negative case for requests to other targets.
  - Fix: added `test_get_pending_excludes_requests_sent_to_other_users`.

- `426d2e54daf776f2`, `5fc0007a1c0c885e` (`Line 115/118: RemoveMethodCall`)
  - Cause: FoF visibility cleanup loop in delete flow was not asserted.
  - Fix: added `test_delete_removes_visibility_for_friend_of_friend_trips`.

## SubscriptionsRepository

- `f33f83c629152a9b` (`Line 35: EqualToIdentical`)
  - Cause: list behavior around `"0"` state coercion was untested.
  - Fix: added `test_list_treats_zero_string_as_state_filter_and_not_null`.

- `d67ef5e7ca5b898b` (`Line 49: IfNegated`)
  - Cause: branch that clamps `from` to current time (today searches) was untested.
  - Fix: added `test_search_public_uses_now_when_trip_day_is_today`.

- `4b6a25e18d53c80a`, `39c82e8a6e59b89c`, `bfcdfc59b0de38cd`, `fead23a67492db61`, `5bd3e5b021c99599`, `67480eeb42185937`, `ceaea2c03b3fff6c`
  - Cause: date window, active-state filter, and recency gate in public search were under-asserted.
  - Fix: added `test_search_public_applies_date_window_state_and_recent_creation_filters`.

- `dfa3484f97c01a8b`, `415c311b42d7720d`, `0d971ac0fb11da7f`, `7d76960a48011f57`
  - Cause: FoF branch relation filtering and OR wiring lacked direct coverage.
  - Fix: added `test_search_fof_trip_includes_friend_and_relative_friend_subscriptions`.

- `1b76ab8186c84b27`, `bbc0392c2ec13fe3`
  - Cause: no-path branch and both distance calls were not covered.
  - Fix: added `test_search_without_path_uses_distance_filters_for_start_and_end_points`.

- `6cd9a519574e6be6`, `1c9b426fe8498e14`, `29420b1c5f9927bd`, `6779b2d5561945a0`, `ad2cfd333cf4b208`, `bd146720a5417793`, `78ce6027d2a4770a`, `d4fa360dd48008e5`, `8c5efeb3827d98d5`, `486b69dbdf41772e`, `9c8bff4b8cc4c12b`
  - Cause: `getPotentialNode` bbox initialization/comparators and lng bounds were weakly asserted.
  - Fix: added `test_get_potential_node_uses_both_lat_and_lng_bounds_with_equality_edges`.

## ConversationRepository

- `347e5c2ad04e5254`, `7f4a6ce0dc5cee48`, `a583a4a54f8550a2`
  - Cause: membership gate in `getConversationFromId` lacked strict behavior tests.
  - Fix: reinforced by `test_get_conversation_from_id_with_user_requires_membership`.

- `1e93b573f4f99f69`, `b5acc029b60ed07a`
  - Cause: `getConversationByTripId` membership branch was untested and ineffective.
  - Fix: added `test_get_conversation_by_trip_id_requires_membership_when_user_provided` and fixed repository logic.

- `65cb6b079b80c6c0` (focused run variant on trip retrieval early return)
  - Cause: method did not guard null trip conversation explicitly.
  - Fix: added `test_get_conversation_by_trip_id_returns_null_when_trip_has_no_conversation` and null return in repository.

- `c34eaf14310d5be2`, `81b051d962da551e`
  - Cause: attach pivot payload default (`read`) was not asserted.
  - Fix: strengthened `test_users_add_user_remove_user`.

- `fb1a852cff8663e3`, `0c16e14d843483fe`, `db2999b2618a91ca`
  - Cause: `matchUser` constraints for private/non-deleted conversation were incompletely asserted.
  - Fix: added `test_match_user_ignores_non_private_and_deleted_conversations`.

- `8fa06f6a1fb7d09f`, `11abb847856a06ed`, `e4c6534df6af4cfa`, `4b765ce76aaa9ed8`, `888e85411dbe27c8`
  - Cause: `userList` nested iteration and search/self filters were uncovered.
  - Fix: added `test_user_list_excludes_self_and_filters_with_search_text`.

- `923ae30fd029094d`, `f7d2b59d8b2e231a`, `fe6d365b386ce4cc`, `40233c5b50f76832`, `b2e48349c3634d25`, `9e7efa4b183e404a`, `5714c44fc3ef4640`, `fb0ef168746086c7`, `5494f1022932dc3c`, `e5486689d57934f9`, `8902979f79da0810`, `a99a6f0833779d83`, `bc364170017908f5`, `5ec2840d296300e1`, `f202a89ff2505006`, `2fed3ea59a4ffe41`, `dee6a17eeeedff27`, `b7ac732f83368cf0`, `bf63b36154353e13`, `736991285f167c75`, `0d19c6b2898b2003`, `b5672d9eed073752`, `46b4eb3f95ab633a`, `ab6806f4c7906aa3`, `a6ef62b9af37fbd3`, `9967a653cd968aa7`
  - Cause: `usersToChat` had many relation/filter/search branches without direct coverage.
  - Fix: added `test_users_to_chat_applies_who_and_search_filters_and_excludes_self`.
