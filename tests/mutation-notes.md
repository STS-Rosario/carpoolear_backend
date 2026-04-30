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

- `getPending` `wherePivot`/`where('state', FRIEND_REQUEST)` guard (`FriendsRepository.php` ~53–55): stale report still showed `RemoveMethodCall` survivors on the state constraint vs friend-id constraint.
  - Cause: negative coverage did not include a target user who already had an accepted friendship row while also receiving a pending request from someone else (both edges reference the same target user id).
  - Fix: added `test_get_pending_excludes_accepted_friends_who_share_target_edge`.

- `426d2e54daf776f2`, `5fc0007a1c0c885e` (`Line 115/118: RemoveMethodCall`)
  - Cause: FoF visibility cleanup loop in delete flow was not asserted.
  - Fix: added `test_delete_removes_visibility_for_friend_of_friend_trips`.

- `FriendsRepository.php` `get` (`~28–46`): empty friend list for `$state`, or search substring matching zero rows.
  - Cause: listings always asserted hits (`by_id`, pagination, substring matches).
  - Fix: added `test_get_returns_empty_when_user_has_no_friends_for_state` and `test_get_returns_empty_when_search_value_matches_no_friend_names`.

- `FriendsRepository.php` `get` (`~34–36`): optional `$user2` id filter when that user is not an accepted friend of `$user1`.
  - Cause: `by_id` hits always used `$f1` that was attached in-setup; no miss-path assertion.
  - Fix: added `test_get_returns_empty_when_friend_user_filter_misses`.

- `FriendsRepository.php` `getPending` (`~50–57`): target user with zero incoming `FRIEND_REQUEST` edges.
  - Cause: pending tests always seeded a request row before listing.
  - Fix: added `test_get_pending_returns_empty_when_no_incoming_requests`.

- `FriendsRepository.php` `add` (`~14–19`): must call `$user1->allFriends()->attach($user2->id, ['state' => $state])` (pending path skips `generateFriendTripVisibility`).
  - Cause: integration tests asserted pivot/visibility rows only on persisted users.
  - Fix: added `test_add_pending_invokes_all_friends_attach_with_state_payload` (mock `User` + `attach` expectation).

## SubscriptionsRepository

- `f33f83c629152a9b` (`Line 35: EqualToIdentical`)
  - Cause: list behavior around `"0"` state coercion was untested.
  - Fix: added `test_list_treats_zero_string_as_state_filter_and_not_null`.

- `SubscriptionsRepository.php` `show` (`~23–26`): missing subscription id.
  - Cause: CRUD round-trip only exercised `show` on persisted ids; omitting `find` could regress without a null expectation.
  - Fix: added `test_show_returns_null_when_subscription_missing`.

- `SubscriptionsRepository.php` `create` / `update` / `delete` (`~13–30`): `return $model->save()` / `return $model->delete()`.
  - Cause: integration tests only asserted successful persists/removals.
  - Fix: added `test_create_returns_false_when_save_fails`, `test_update_returns_false_when_save_fails`, `test_delete_returns_false_when_delete_fails`.

- `SubscriptionsRepository.php` `create` / `update` / `delete`: successful-path forwarding (`save()` / `delete()`).
  - Cause: false-path mocks existed; explicit invoke expectations on successful returns were missing for RemoveMethodCall clusters.
  - Fix: added `test_create_invokes_save`, `test_update_invokes_save`, `test_delete_invokes_delete`.

- `SubscriptionsRepository.php` `list` (`~33–39`): user with zero subscriptions (`$active == null`), or `where('state', true)` when user only has inactive rows.
  - Cause: list tests always seeded matching subscription rows before querying.
  - Fix: added `test_list_returns_empty_when_user_has_no_subscriptions` and `test_list_returns_empty_when_active_filter_matches_no_rows`.

- `SubscriptionsRepository.php` `list` (`~33–39`): **`false` filter vs null/unfiltered branch**.
  - Cause: PHP loose `$active == null` is true for boolean `false`, so `list($user, false)` incorrectly returned every subscription (same as `$active === null`). Assertions only covered `null`, `true`, and string `'0'`.
  - Fix: guard with `$active === null`; added `test_list_filters_by_false_when_explicit_bool_false`.

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

- `userList` `$search_text === null` (`ConversationRepository.php` ~159): `preg_match` pattern becomes `//i` (matches every subject); removing inner search predicate otherwise collapses to weak/no assertions when only one peer existed.
  - Cause: previous coverage always passed a concrete substring; null-search semantics were undocumented and easy to break silently.
  - Fix: added `test_user_list_null_search_text_includes_every_other_participant`.

- `923ae30fd029094d`, `f7d2b59d8b2e231a`, `fe6d365b386ce4cc`, `40233c5b50f76832`, `b2e48349c3634d25`, `9e7efa4b183e404a`, `5714c44fc3ef4640`, `fb0ef168746086c7`, `5494f1022932dc3c`, `e5486689d57934f9`, `8902979f79da0810`, `a99a6f0833779d83`, `bc364170017908f5`, `5ec2840d296300e1`, `f202a89ff2505006`, `2fed3ea59a4ffe41`, `dee6a17eeeedff27`, `b7ac732f83368cf0`, `bf63b36154353e13`, `736991285f167c75`, `0d19c6b2898b2003`, `b5672d9eed073752`, `46b4eb3f95ab633a`, `ab6806f4c7906aa3`, `a6ef62b9af37fbd3`, `9967a653cd968aa7`
  - Cause: `usersToChat` had many relation/filter/search branches without direct coverage.
  - Fix: added `test_users_to_chat_applies_who_and_search_filters_and_excludes_self`.

- `usersToChat` admin disjunct (`ConversationRepository.php` ~171–173 `where('is_admin', true)` among trip/passenger ORs).
  - Cause: chat tests only exercised accepted-friend edges; dropping the admin clause still passed because friends satisfied the query.
  - Fix: added `test_users_to_chat_includes_admin_matching_search_without_friend_edge`.

- `usersToChat` public-trip and shared-ride OR branches (`ConversationRepository.php` ~176–195): `orWhereHas('trips', … PRIVACY_PUBLIC …)` and `orWhereHas('passenger.trip.user', …)`.
  - Cause: earlier tests covered friends + admin only; removing the trips block or the passenger→trip→driver join still passed.
  - Fix: added `test_users_to_chat_includes_driver_with_public_trip_without_friend_edge` and `test_users_to_chat_includes_accepted_passenger_on_seekers_trip_without_friend_edge`.

- `usersToChat` FoF nested `trips` filter (`ConversationRepository.php` ~178–188) vs inner `passengerAccepted` (`~189–191`).
  - Cause: PUBLIC/driver Passenger branches didn’t force FoF graph traversal (`user.friends.friends`) nor trip-row qualification solely via `passengerAccepted` without PUBLIC/`trips`-closure redundancy where overlapping outer passenger existed.
  - Fix: added `test_users_to_chat_includes_fof_trip_driver_via_friend_of_friend_without_direct_friendship` (FoF trip + bridge friendship + asserted non-direct friends edge) and `test_users_to_chat_includes_trip_driver_when_seeker_is_accepted_passenger_via_trips_closure` (`PRIVACY_FRIENDS` trip + viewer passenger targets inner closure distinctness).

- `ConversationRepository.php` `store` / `delete` (`~11–18`): `return $conversation->save()` / `return $conversation->delete()`.
  - Cause: integration tests only asserted successful paths on real models.
  - Fix: added `test_store_returns_false_when_save_fails` and `test_delete_returns_false_when_delete_fails`.

- `ConversationRepository.php` `store` / `delete`: successful-path `save()` / `delete()` invoke.
  - Cause: false-path mocks existed without complementary successful expectations.
  - Fix: added `test_store_invokes_save` and `test_delete_invokes_delete`.

- `ConversationRepository.php` `getConversationsByTrip` (`~68–73`): zero rows for `trip_id`.
  - Cause: tests always created conversation rows before listing by trip.
  - Fix: added `test_get_conversations_by_trip_returns_empty_when_trip_has_no_conversations`.

- `ConversationRepository.php` `matchUser` (`~112–124`): `first()` when users share no qualifying conversation row.
  - Cause: negative tests relied on wrong type/deleted rows, not on absence of any shared membership.
  - Fix: added `test_match_user_returns_null_when_users_have_no_shared_conversation`.

- `ConversationRepository.php` `getConversationsFromUser` (`~23–35`): `has('messages')` excludes conversations without messages; empty paginator vs `get()`.
  - Cause: listing tests always inserted messages before calling `getConversationsFromUser`.
  - Fix: added `test_get_conversations_from_user_returns_empty_when_no_conversations_with_messages`.

- `ConversationRepository.php` `updateTripId` (`~139–144`): must call `$conversation->save()` after assigning `trip_id`.
  - Cause: integration tests asserted persisted trips only on factory rows.
  - Fix: added `test_update_trip_id_invokes_save` (`mock(Conversation::class)->makePartial()` + `save()` expectation).

- `ConversationRepository.php` `addUser` / `removeUser` (`~83–91`): must forward to `users()->attach(..., ['read' => true])` / `detach(...)`.
  - Cause: pivot outcomes were asserted via DB membership only; omitting `attach`/`detach` could survive without relation expectations.
  - Fix: added `test_add_user_invokes_attach_with_read_true_payload` and `test_remove_user_invokes_detach` (mock conversation `users()` relation).

## TripSearchRepository

- Cluster `TripSearchRepository.php` `trackSearch` (stale report ~1050–1078 in `tests/coverage/20260428_2310.txt`): `total() ?? count()`, `$trips->count() > 0` + `seats_available <= 0` filter, `$searchData` keys / `create()` return.
  - Cause: only LengthAwarePaginator + single carpooleado was exercised; `total()` must exist on `$trips` (calling `$trips->total()` throws before `??` on a bare Collection — branch matters when `total()` returns `null`). Persisted columns were not asserted in one combined DB shape check for RemoveArrayItem clusters on payload keys.
  - Fix: added `test_track_search_falls_back_to_count_when_total_returns_null` (anonymous `$trips` stub with `total()` returning `null`), `test_track_search_counts_each_full_trip_as_carpooleado`, `test_track_search_persists_search_payload_columns_for_array_remove_mutants`.

- `TripSearchRepository.php` `trackSearch` (`~19–29`): `LengthAwarePaginator` with **empty current page** but **`total() > 0`** — `amount_trips` uses `total()` while carpooleado filter gates on **`$trips->count()`** (page slice).
  - Cause: tests always passed paginator pages that included trip models whenever `total > 0`.
  - Fix: added `test_track_search_skips_carpooleado_scan_when_current_page_has_no_items`.

- `TripSearchRepository.php` `trackSearch` (`~31–43`): must call `$this->create($searchData)` with the assembled payload (delegate path distinct from static `TripSearch::create` coverage alone).
  - Cause: persistence tests exercised end-to-end rows but not an explicit expectation that `create` receives every `searchData` key produced inside `trackSearch`.
  - Fix: added `test_track_search_delegates_to_create_with_full_search_data_payload` (partial repository mock + `create` expectation + `TripSearch::create` in callback).

- `TripSearchRepository.php` `trackSearch` default args (`tests/coverage/20260428_2310.txt` ~20393–20423): `FalseToTrue` / `DecrementInteger` / `IncrementInteger` on `$clientPlatform = 0` and `$isPassenger = false` when omitted (`04bb992cfddae78b`, `96a25bf38851235d`, `cf380555cf0aa34f`).
  - Cause: `test_track_search_null_user_and_null_search_date` asserted model fields but not persisted `client_platform` / `is_passenger`, so default-parameter mutants could survive.
  - Fix: extended that test with `assertDatabaseHas('trip_searches', …, 'client_platform' => 0, 'is_passenger' => 0)`.

- `TripSearchRepository.php` `trackSearch` (`tests/coverage/20260428_2310.txt` ~20429–20463): `GreaterToGreaterOrEqual` / `DecrementInteger` / `IncrementInteger` on `$trips->count() > 0` (`560f96995275eed4`, `080c4253f39866ce`, `cfe735486a748594`).
  - Cause: carpooleado branch was only proven with multi-item pages or empty pages; a **single-item** page must still enter the filter so `> 1` or other comparator mutations drop carpooleado counts incorrectly.
  - Fix: added `test_track_search_counts_carpooleados_when_current_page_has_exactly_one_trip`.

## MessageRepository

- Cluster `MessageRepository.php` (`tests/coverage/20260428_2310.txt` ~1080–1108): `changeMessageReadState` / `createMessageReadState` pivot payloads, `getMessagesUnread` `$item->pivot->read == 0`, `markMessages` bulk `update` keys.
  - Cause: survivor mutants removed pivot `read` keys or dropped `updated_at` from the bulk update without assertions on raw `user_message_read` / `conversations_users` rows; loose `== 0` vs `=== 0` was not distinguished when `read` is stored as `'0'`.
  - Fix: added `test_mark_messages_updates_user_message_read_updated_at`, `test_change_message_read_state_persists_read_flag_in_user_message_read`, `test_create_message_read_state_inserts_read_into_user_message_read`, `test_get_messages_unread_includes_conversation_when_pivot_read_is_loosely_zero`.

- `MessageRepository.php` `store` / `delete` (`~13–20`): `return $message->save()` / `return $message->delete()`.
  - Cause: integration tests only asserted successful paths.
  - Fix: added `test_store_returns_false_when_save_fails` and `test_delete_returns_false_when_delete_fails`.

- `MessageRepository.php` `store` / `delete`: successful-path `save()` / `delete()` invoke.
  - Cause: false-path mocks existed without complementary successful expectations.
  - Fix: added `test_store_invokes_save` and `test_delete_invokes_delete`.

- `MessageRepository.php` `getMessages` (`~23–34`): conversation with zero message rows.
  - Cause: pagination tests always seeded messages first.
  - Fix: added `test_get_messages_returns_empty_when_conversation_has_no_messages`.

- `MessageRepository.php` `getUnreadMessages` (`~37–42`): `whereHas` unread pivot yields zero messages.
  - Cause: unread path always paired an unread row with a hit.
  - Fix: added `test_get_unread_messages_returns_empty_when_no_unread_for_user`.

- `MessageRepository.php` `getMessagesUnread` (`~55–80`): no unread conversation memberships (`$conversations_id` empty) or only read pivots.
  - Cause: tests always attached at least one unread conversation before asserting messages.
  - Fix: added `test_get_messages_unread_returns_empty_when_user_has_no_conversations` and `test_get_messages_unread_returns_empty_when_all_conversations_marked_read`.

- `MessageRepository.php` `markMessages` (`~83–98`): `whereHas` unread=false yields empty `pluck('id')`; bulk update runs with empty id list / zero matching pivots.
  - Cause: tests always seeded at least one unread pivot before calling `markMessages`.
  - Fix: added `test_mark_messages_completes_when_no_message_ids_match_unread_pivot` (messages only attached with `read => true`).

## PassengersRepository

- Cluster `PassengersRepository.php` (`tests/coverage/20260428_2310.txt` ~1166–1259): `newRequest` create payload keys; `cancelRequest` `==` vs constants (`EqualToIdentical`); `getPendingRequests` / `getPendingPaymentRequests` trip joins + `trips.deleted_at`; `userHasActiveRequest` `whereIn` states.
  - Cause: create-array RemoveArrayItem mutants had only scalar asserts on the returned model; cancel-branch mutants compared ints strictly vs `'0'` / `'3'` request payloads from callers coerced to string; listing helpers lacked explicit exclusion proof when the joined trip is soft-deleted; `WAITING_PAYMENT` was not asserted separately inside `userHasActiveRequest` coverage.
  - Fix: strengthened `test_new_request_creates_pending_passenger_row` with `assertDatabaseHas`; added `test_cancel_request_matches_reason_via_loose_equality_for_string_literals`, `test_get_pending_requests_without_trip_id_excludes_soft_deleted_trips`, `test_get_pending_payment_requests_excludes_soft_deleted_trips`, `test_user_has_active_request_includes_waiting_payment_state`.

- `PassengersRepository.php` `getPassengers` (`~11–20`): trip rows exist but none in `STATE_ACCEPTED`; non-paginated and paginated branches.
  - Cause: tests always seeded accepted passengers before listing; dropping `whereIn('request_state', …)` could survive without a zero-hit assertion.
  - Fix: added `test_get_passengers_returns_empty_when_trip_has_no_accepted_passengers` and `test_get_passengers_paginates_empty_when_no_accepted_passengers`.

- `PassengersRepository.php` `getPendingRequests` (`~23–45`) with concrete `$tripId`: no rows in `STATE_PENDING`.
  - Cause: trip-level test always inserted a pending passenger.
  - Fix: added `test_get_pending_requests_for_trip_returns_empty_when_no_pending_rows`.

- `PassengersRepository.php` `getPendingRequests` (`~25–39`) with concrete `$tripId`: **zero** `trip_passengers` rows for that trip (stricter than “no pending” when only accepted rows exist).
  - Cause: empty path always went through “accepted passenger but not pending” rather than an empty relation.
  - Fix: added `test_get_pending_requests_for_trip_returns_empty_when_trip_has_no_passenger_rows`.

- `PassengersRepository.php` `tripsWithTransactions` (`~180–206`): user with no past trips joined via passengers carrying `payment_status`.
  - Cause: distinct-hit tests always seeded matching passenger+trio rows first.
  - Fix: added `test_trips_with_transactions_returns_empty_when_user_has_no_qualifying_rows`.

- `PassengersRepository.php` `getPendingPaymentRequests` (`~49–63`): user with zero `WAITING_PAYMENT` passenger rows on qualifying trips.
  - Cause: listing tests always created a waiting-payment row first.
  - Fix: added `test_get_pending_payment_requests_returns_empty_when_user_has_no_waiting_payment_rows`.

## TripRepository (current batch)

- `47a4022bfb577c5a`, `a50255ec77726d09`, `33b99591f429010d`, `7ab8d1032746dbfa`, `c971ded4c0583849`, `dd8743ead8f87fde`, `14701d7b0afa6c43`, `9e6cb9312e8e70ea`, `e0900113fa619282`, `fce18506ce2a3b67`, `2f31f58e34bc7f68`, `bbdfa6dd5309dc76`, `b687fb0c758f9ff7`, `1294a7e0b757765f`, `13bffdc93611a1bd`, `2561f9ba60928e7c`, `aa1ba96b77874b63`, `bb988e6606ef287b`, `64854536d7731d76`, `926f5eb76fa1c5d2`, `ee8770e106619a2a`, `86a3522102bd856b`, `4abda33c3ccae2f5`, `0280f49e5e9aa5f6`, `168a1c682d274fec`, `9332ef5aa60d7c7b`, `726f02044afec66a`, `74f70ee8c58fdc8d`
  - Cause: private `getPotentialNode` math and bbox comparator branches were not directly exercised.
  - Fix: added `test_get_potential_node_private_bbox_logic_uses_lat_lng_ranges` (reflection call to validate returned node is inside bbox only).

- `5656ee9b01173343`, `85d71c45fe613abd`, `18c59a50fd2ffaeb`, `7bfc0ab3a97dea41`, `7242bbee8560e5a1`, `853a1c66244b9770`, `37e68b0071fa371b`, `53c27d17ceefefc7`, `92a7cb8cf0ce32c2`, `7409a27f9aff2ae1`, `53d5810f9a02f52f`, `3aab526f6920f8c0`
  - Cause: friend visibility generation branches (FoF vs friends-only) and SQL inserts were weakly asserted.
  - Fix: added `test_generate_trip_friend_visibility_fof_and_friends_only_branches_insert_rows`.

- `a7a74d3095388168`, `b06e7346ab0b7439`, `4be0f43c62497887`, `0213b9ad10d10530`, `5554cc2241bfb082`, `022258a4506e3501`, `a83b5da5b120f035`, `6938af9e22ede182`, `05e9c3d11001b524`, `a740b8f320284c77`, `9d95fa6e7a3a63ff`, `da58e72d61dacac0`, `4ac785b2268a41f5`, `4a534627977977e3`, `071ba109676bd985`
  - Cause: max seat-price guard/cap logic in `create` was not covered across condition and arithmetic mutations.
  - Fix: added `test_create_caps_seat_price_at_maximum_when_module_enabled`.

- `c420b4523347fb9b`, `054956c69594930e`, `3bc7a0ac2cd873e1`, `4bc087b221ea14d1`, `330f86c4e850f0f9`, `d3a6deda630c0225`, `1301ca6a05995d26`, `9b0a406f82a305f3`, `989e733cfc9ee959`, `ebad617f35c5726b`, `e416ada5cc106318`, `85d42995c6d515f4`
  - Cause: branch where cap assignment is skipped (disabled module/non-applicable path) was uncovered.
  - Fix: added `test_create_keeps_seat_price_when_cap_not_required_or_module_disabled`.

- `97d4c2cdee6ca23d`, `e53a5df21a05bb4e`, `360811401013af7d`, `4123fa9d994e5a5b`, `1a6d2f2a81d01d31`, `2b10aa7ab3776cd5`, `41ad6e0ea218715e`, `ebbc4167c595fae9`, `d25ccf133496e175`
  - Cause: payment-trigger branch in `create` (route requires payment + threshold reached) was not directly asserted.
  - Fix: added `test_create_sets_awaiting_payment_and_preference_when_route_requires_sellado`.

- `0080679657fc2dd9`, `e0b1a2010fa804e9`, `26405b9a722298d8`, `0e7b59bfbe29fe09`, `ab3144e108c0e760`, `74b84d645bbeaaa3`, `79ecceb2aeec68e0`, `e478c1e7b52a8b76`, `a62aa6e9192c3220`, `d7148f2bcf5c0dd0`
  - Cause: route loop and endpoint extraction/cast logic in `create` had no direct route-creation assertion.
  - Fix: added `test_create_creates_and_syncs_routes_from_points_json_address_ids`.

- `c3ab61b50d2e29e6`, `4e2796064a396e48`, `2de3deecc5bf329b`, `52810aad74f43a6a`, `7afd019bd08af694`
  - Cause: existing-route branch in `create` (including processed-route event dispatch and trip route sync) lacked a direct assertion.
  - Fix: added `test_create_reuses_processed_route_and_dispatches_create_event`.

- `a54deebcf1014a06`, `490aec5db92a5b6e`, `e705f32825014ed3`, `f07be5a076a1191f`, `cbefb735437de83c`
  - Cause: new-route branch in `create` did not assert `processed=false`, route persistence, and syncing both endpoint nodes.
  - Fix: strengthened `test_create_creates_and_syncs_routes_from_points_json_address_ids` with route `processed` and `route_nodes` assertions.

- `b06214e1744b47ae`, `778fc72ecde2c4b5`, `e86bce3766a81131`, `4cd0f45423a259c2`, `1a556c3e6c5d37a6`, `852234564b8b2945`, `8c6e8823c8e66b93`
  - Cause: `update` path did not assert old-points threshold (`count >= 2`) and old-route lat/lng mapping used for sellado checks.
  - Fix: added `test_update_skips_new_payment_when_old_route_already_required_sellado`.

- `829b7c5c1cd18c62`, `973452f72b559beb`, `d4c9e20293c2c15c`, `36118027b79c2e79`, `13792bbf188a3fc8`, `e99dba43208657df`, `ac0c7cb7c8c84488`, `a8347453eee474e0`, `013110593ed7a715`, `67c5f5cedbe5f96e`, `c7aac5f5db44cfc4`, `1669a15c00663974`, `1103c83ab827cb26`, `0f09716a552e0cc8`, `a03e507eb14a99f0`, `27d3cb8394955dee`
  - Cause: `update` seat-price cap logic lacked direct assertions for gate conditions, payload `total_seats` coalesce behavior, rounding math, and over-cap/below-cap comparison outcomes.
  - Fix: added `test_update_caps_price_with_payload_total_seats_and_rounding_rules`.

- `504111811d143a35`, `adb294f1bb793f6c`, `9a5ded833c49409e`, `efaf1746ee6762ca`, `99996c05fe1ad1cc`, `8e05f298d0ad73bd`, `d95e5bc6b1d33820`, `48013067c3ce547d`
  - Cause: `update` path lacked direct assertions for recommended-price persistence, delete/add/regenerate point flow, and lat/lng pair mapping passed to sellado checks.
  - Fix: added `test_update_replaces_points_persists_recommended_price_and_maps_payment_coords`.

- `36c540d16cc674f5`, `681e7bcd48bf7b7f`, `b50e9041e39aac2e`, `77b52754499d420c`, `59c98589c9da49eb`, `961cc23104e94a97`, `879b7f380fb694b8`, `447b75ab63a85945`, `189953df07063235`, `c29223e821b10dea`, `ba5bfa4ac32943cd`
  - Cause: `update` sellado-transition guard lacked strict assertions across boolean gates (`module`, new/old route status, threshold, state) and existing `payment_id` handling.
  - Fix: added `test_update_triggers_new_sellado_payment_on_non_paid_to_paid_transition` and `test_update_does_not_trigger_sellado_when_module_disabled_even_if_route_changes_to_paid`.

- `a38f3aa02684a67a`, `8f0f4900b81196c4`, `3922f273b08381d3`
  - Cause: `update` path with previously completed sellado payment lacked direct assertions for the `selladoAlreadyPaid` branch persistence (`needs_sellado=true` + save) without creating a new preference.
  - Fix: added `test_update_marks_sellado_needed_without_new_preference_when_completed_payment_exists`.

- `45f27ca691ab564d`, `5bdf31da7f173533`, `2c4d45590fbb4491`, `2095960def72d398`, `f9febe68e921584f`, `05f9966842a91f2c`, `5e5980987ced1592`, `2ff5b8a00050b688`, `2b73a9ae7652cdd8`
  - Cause: `update` branch for routes that no longer need sellado lacked assertions for strict elseif gating and payment-state reset behavior (`needs_sellado`, payment states list, reset/save flow).
  - Fix: added `test_update_clears_payment_state_when_route_no_longer_needs_sellado`.

- `f1e3920c718877f8`, `461b2b2b2014b253`, `81ebf36ce14d0d8e`
  - Cause: `generateTripPath` did not have a direct negative/zero node-id filter assertion, leaving `id > 0` threshold mutations unprotected.
  - Fix: added `test_generate_trip_path_ignores_zero_or_negative_node_ids`.

- `b73f090484a0f6cf`, `cdd40d6198be223e`, `88d43ec2386c79f0`, `a9916da59882f03d`, `2e4c80cf0156b94a`, `5282c105ca53e94c`, `da0260725194a1cd`
  - Cause: `show` relation eager-loading for admin/non-admin branches lacked explicit assertions, so relation list mutations survived.
  - Fix: strengthened `test_show_includes_soft_deleted_trip_for_admin` and added `test_show_for_non_admin_eager_loads_user_and_points_only`.

- `016f0f4690481e55`, `af11e441e24a3bdf`, `3389b1d9a73e51d3`
  - Cause: `index` lacked direct assertions for raw-expression key detection (`strpos(..., '(')`) and conditional eager loading via `$withs`.
  - Fix: added `test_index_supports_raw_expression_keys_containing_parenthesis` and `test_index_applies_withs_only_when_requested`.

- `ea0d77bef43a753b`, `1080831d62dc2928`, `0fffadb86edb82af`, `63d3a1d32f4d69bb`, `24ee753e8e86611b`, `1d848060b5053ebd`, `81e2ab41fcb80d60`, `b58f5484eb653705`, `f96c9cc80df39a62`, `b50f19e67dac5560`, `e3ef27b53f339ed4`
  - Cause: `getTrips` driver/passenger branches lacked explicit assertions for driver filter, passenger join constraints, ordering, and eager-loaded relation set.
  - Fix: strengthened `test_get_trips_driver_returns_future_trips_for_user` and `test_get_trips_passenger_returns_trips_where_user_accepted`.

- `4c662dd5460a827f`, `4fe1e9c3638b2f2c`, `a4f5ce2fdb113d99`, `f77fb9baf44caaf2`, `7075ee4766e78c24`, `20bacc8888508a5f`, `128d4a1954c7ce68`, `018e7fafd1bdebbe`, `fb53d9c82bbdf334`
  - Cause: `getOldTrips` lacked direct assertions for driver filter/order/select and eager-loaded relation set parity with `getTrips`.
  - Fix: strengthened `test_get_old_trips_excludes_weekly_schedule_and_past_only` with ordering and eager-load assertions.

- `41b3ff3a3b90d001`, `812d135b57a68dc6`, `adb8efe4d6d7b5c5`, `fe67f74c929986cf`, `45c5a98301e1e6bc`, `e38c3f8f43a330bb`, `8415cdc00871d360`
  - Cause: initial `search` branch lacked direct assertions for routes eager-load baseline, `is_passenger` filter application, admin trashed gating, and single-bound date-range branching.
  - Fix: added `test_search_applies_is_passenger_filter_and_keeps_routes_eager_loaded` and `test_search_with_admin_flag_controls_trashed_and_supports_single_date_bounds`.

- `f72a863783d0d92e`, `6fdd97778d011e84`, `a36ebef18814d78d`, `444bd49df625259c`, `b80f5f470cf3e9c3`, `656042282cff9c7f`
  - Cause: `search` date-window internals lacked direct assertions for from/to inner guards and ordering calls, plus strict-date ordering branch.
  - Fix: added `test_search_applies_from_to_and_strict_date_ordering_paths`.

- `48d9371fc12ff975`, `766b751ecf371bed`, `c03ae62dc7ac4fa3`
  - Cause: weekly-schedule search branch lacked explicit assertions for bitwise `whereRaw` filtering and ordering.
  - Fix: added `test_search_weekly_schedule_uses_bitwise_filter_and_orders_by_trip_date`.

- `e730140a0b60256a`, `6ccb38ae02344061`, `be6f1f4ef1b631d6`, `e3727bfc495d0726`
  - Cause: default `search` branch (`history` absent) lacked explicit assertions for active-trip filtering and its ordering side-effect.
  - Fix: added `test_search_default_branch_filters_active_unless_history_and_orders_by_trip_date`.

- `ffe21dba8f63af4a`, `4474a4cffd3d8291`, `503862ec6c4c74f2`
  - Cause: sellado visibility guard in `search` lacked direct assertions for null-user bypass and owner-specific visibility of unpaid/non-ready trips.
  - Fix: added `test_search_with_null_user_does_not_apply_owner_sellado_visibility_guard` and `test_search_user_scope_keeps_owner_unpaid_trip_and_hides_other_unpaid_trip`.

- `4a586973694e66f8`, `e0723334fdd539f6`, `e8e8ec49e4a53995`, `73e03ed3b8f621e0`, `a75ddf8beaf6a200`
  - Cause: non-admin privacy gate in `search` lacked direct assertions for guard shape (`$user && !$user->is_admin`) and nested visibility block execution.
  - Fix: added `test_search_non_admin_applies_privacy_visibility_filter_but_admin_bypasses_it`.

- `92a2e80bb1a380af`, `94aeb181cd5cc635`, `e92743782077c92f`, `4150b4f8c0e25d3c`
  - Cause: nested non-admin search clauses lacked direct assertions for state visibility (`ready/paid/null/owner`) and owner/public-or-visible privacy constraints.
  - Fix: added `test_search_non_admin_applies_state_and_owner_visibility_subclauses`.

- `d98c00d01c671152`, `34e9ab946097227e`, `5debf278b16d4de1`, `7a81862f468ba059`, `86ba949ffb10ff4b`
  - Cause: `search` branch combining `origin_id` and `destination_id` lacked direct assertions for integer casts and both path LIKE alternatives (adjacent and with intermediate stops).
  - Fix: added `test_search_with_origin_and_destination_ids_filters_by_path_patterns`.

- `43bb9e39680b0bca`, `9e993977d9bc3a2f`, `acd6de4470f85e62`, `09303e1e4d50988f`
  - Cause: `search` branch for origin-only/destination-only route filtering lacked direct assertions for `whereHas('routes')` constraints on `routes.from_id` and `routes.to_id`.
  - Fix: added `test_search_with_origin_or_destination_id_filters_using_routes_relation`.

- `118c42b7eff9cc5c`, `25abd04f763b5a85`, `2f94cd00d41ddd88`, `10d289a5ac31750f`, `e770bddfccf0a6de`, `3518385e3e63f800`, `c9ab669d96c569d8`, `b353ad2c4f16d77d`
  - Cause: final `search` eager-load bundle lacked direct relation-loaded assertions on returned items (including nested `user.accounts`).
  - Fix: added `test_search_eager_loads_full_relation_bundle_on_results`.

- `437c5d733842c7e5`, `b9cdaac4a7dbccbc`, `6e1934bfbec86451`, `60ea7ce4b478ef76`, `ec24c222f23d095b`, `e2221c4cb194b0fe`, `8b730257b8331ca9`, `bb32ca2e812ac2ff`, `4b04e2ea570750c1`, `c3690705b6358360`
  - Cause: `search` geo fallback (`origin_lat`/`origin_lng`, `destination_lat`/`destination_lng`) lacked assertions for both-coordinate guards, default vs custom radius behavior, and actual location filter application.
  - Fix: added `test_search_origin_geo_filter_requires_both_coords_and_uses_default_radius` and `test_search_geo_filters_use_custom_radius_for_origin_and_destination`.

- `5756b28e7b58afd3`, `24c2b1265c4701e0`, `3893054afa515eff`, `e0ff1b10b2e2196c`, `ee58a9bd230d3983`, `615998a13b0f52b3`, `cea9620ede65aaa8`
  - Cause: private `whereLocation` internals lacked direct behavioral checks for origin/destination point selectors (`min(id)` / `max(id)`), enclosing `whereHas`, and spherical distance raw filter application.
  - Fix: added `test_where_location_origin_filters_using_first_point_only` and `test_where_location_destination_filters_using_last_point_only`.

- `a1d5890d79807969`, `5cb6dc93314238e7`, `27d40032f441d215`, `d1fa14096c6e2d91`, `c96024f8aad13239`, `954ce6330e6a1642`, `f4095bba926c6437`, `4331f3683f0507a0`, `468df71dbde57f7a`, `e0d241320e3c07b1`, `bf1febad9ce7477e`, `9c8d7d9c44d50c6e`, `ea40d19b1507a512`, `717fd9c6370fabda`, `99f4e9993a3b4be2`, `80142f3737eeaa25`
  - Cause: `whereLocation` numeric boundary logic lacked tests that pin both default radius behavior (implicit 1000m) and minimum-radius enforcement when callers pass a smaller value.
  - Fix: added `test_where_location_default_distance_keeps_1000m_boundary_behavior` and `test_where_location_enforces_minimum_radius_of_1000_even_if_lower_is_passed`.

- `7941bcae741806f0`, `c309cdbc3f4a1d6b`, `9ebb4531e7d1c291`, `72b0034215a62708`, `654b282c1061f652`, `d93487fbf87ab0b8`, `f8bfa1bb6d26b1d1`, `8f105c8052bfe1e9`, `a2036bb2730c0857`, `0ffcd91ecaffadb6`, `5a014e97c5e72b29`, `7afd019bd08af694`, `85adb24d944efdf5`
  - Cause: `create` route-loop coverage lacked edge assertions for `getPotentialNode($points[$i-1])`, strict positive id guards (`> 0`), conditional `routes()->sync($routeIds)`, and the unconditional final `generateTripFriendVisibility($trip)` call.
  - Fix: added `test_create_route_loop_uses_previous_point_for_origin_and_accepts_node_id_one`, `test_create_route_loop_uses_previous_point_when_resolving_potential_nodes`, and `test_create_skips_route_sync_when_any_potential_node_id_is_non_positive`.

- `244dbf47a10aaaf2`, `bd13423c0006dea7`
  - Cause: `update()` did not have assertions for (a) keeping `$oldRouteNeedsPayment` false when fewer than two stored stops mean the flag is never recomputed, and (b) entering the `if ($points)` block so prior stops are loaded and `doStopsRequireSellado` runs twice (old route then new route) when enough stored stops exist.
  - Fix: added `test_update_preserves_false_old_route_payment_flag_when_stored_points_are_single_stop` and `test_update_consults_old_route_sellado_when_points_payload_present_and_two_or_more_stored_stops`.

- `0e3418e2b45fa6dc`, `8436b725741c95e3`, `0b111388692a64e7`
  - Cause: `getTripInfo()` route-cache short circuit (`if (! $cacheBypass)`, RouteCache lookup, `if ($cachedRoute)` early return) had no integration-level coverage; mutations skipped caching or skipped returning cached `route_data`.
  - Fix: added `test_get_trip_info_returns_cached_route_data_when_route_cache_row_valid`.

- `f77d59641a69cb82`, `9a8cdb4e5cd63213`
  - Cause: cache-bypass else branch in `getTripInfo()` (`cache BYPASS enabled` log payload) was unexercised, so mutations removing the log call or the `hashed_points` payload item survived.
  - Fix: added `test_get_trip_info_cache_bypass_ignores_cached_row_and_calls_osrm` to force bypass on, seed cache, and assert OSRM-faked result is used instead of cached data.

- `1563863da77829c1`, `e8acd9d34035f62d`, `976b2426fae950ee`, `0f03d952d318404e`, `00e5848889b379f9`, `629c3eabdd90f6e4`, `4776f4d8b8e5da4e`, `8163c34d58696ce0`, `f7416879bad499f9`, `a27c8a6a3e231537`, `f91ebeb3882f4f02`, `386c3bf9c3745cc5`, `d08504b5179cd931`, `e54932e12ac46657`, `e1f9e3574376ac69`, `01452804cdceb277`, `249c2e1dde111f8c`, `b06e80e99f13f1f9`, `26b9e34b96b5aa3f`, `640a19d6532cb984`, `504e4f0fb10d4d2c`, `8c763f6d0e3389a7`, `0a5fdf2be6214c2e`, `a079723bb3746a71`
  - Cause: `getTripInfo()` request-context logging had no strict assertions for non-array input handling (`points_count`, casted bypass flag) and full debug payload integrity (`hashed_points`, `cache_key_length`, preview truncation/substring behavior, original `points` value).
  - Fix: added `test_get_trip_info_logs_request_context_for_long_non_array_input_with_cache_hit` with a long non-array input, cache-hit short-circuit, and exact `Log::debug` context assertions.

- `9da9f1812a4bfbc4`, `bfe5bc3acbce77a9`, `e3c3b4d9ae2338e3`, `3184aa4f26380035`, `097f523e9d0f7093`, `22d996ef6e87bf03`, `5f57d5854dd7bc75`, `7c55a80c84bc5322`, `e0d15319559d5e25`, `e2ff81a4c1c064d6`, `e49aa53b4d20f00f`, `0274e8fcd524cd8b`, `137bd3e03ec40d42`, `14ab95c39ad03b82`, `d0aebab113b2de06`, `81542e43b09b523e`
  - Cause: `getTripInfo()` OSRM-success path lacked explicit assertions for coord-string assembly (`lng,lat` + `;` delimiters), cache-miss logging payload, strict `'route'` status gate, and early-returning `storeTripInfoSuccess(...)` with numeric distance/duration casts.
  - Fix: added `test_get_trip_info_builds_osrm_coords_and_returns_osrm_success_payload` with a 3-point payload, OSRM fake response, URL assertion for exact coords path, and response assertions.

- `2796ba07e4ff899c`, `b28740ad124f48d1`, `c851ab7077b33764`, `69aa2299d2e8a744`, `690c463c384bb84d`, `fda989c9593662e6`, `86466b659c77ba3d`, `f0b108ab86099fb5`, `e4d3403098efeccc`
  - Cause: `getTripInfo()` mapbox fallback path was missing direct assertions for `isEnabled()` gate, fallback log payload (`hashed_points`, `osrm_status`), non-null metrics guard, and early return through `storeTripInfoSuccess(...)` using mapbox distance/duration values.
  - Fix: added `test_get_trip_info_uses_mapbox_fallback_when_osrm_has_no_route` with OSRM `NoRoute` response, enabled mapbox mock returning metrics, and success-response assertions.

- `cdde5206d4d0f164`, `7e288ac045dd5f87`, `06d907bc19d86874`, `7bcb0158c95f44a1`, `57b3e4498ff0bac9`, `db13cb03931665f6`, `4cc47adb0ded1625`, `15552aeb3d878093`, `9188cb912a5be281`, `e7b00afbc4bb9ab5`, `35a723d86c7d9376`, `aa533d56f6cc8a53`, `d542d1791f5a8c7b`, `5b6bb0d6f3a36824`, `457e70cf0c157238`, `285eb582a566b4ed`, `aae7e5e2ed38919d`, `c6671c30c3dafa0c`, `c84eefdde872a417`, `5e089672117921a5`, `1063db96223433cd`, `39072298f6adf7ed`, `fff5a713866a13a8`
  - Cause: `getTripInfo()` had no direct tests for the `osrm_unreachable` early-return branch and the `osrm_no_route` fail-response/cache-write branch (payload extraction, logs, fail-response shape, cache write guard, and final return).
  - Fix: added `test_get_trip_info_returns_service_unavailable_when_osrm_unreachable_and_mapbox_disabled` and `test_get_trip_info_returns_not_found_and_caches_fail_response_when_osrm_no_route`.

- Cluster `TripRepository.php` **794–867** (`requestOsrmForTripInfoCoords`): e.g. `72265ff1791736f6`, `68230deb587359a8`, `f6b19801eab80bfa`, `51a2c0ff375f8dea`, `7609f53a3c60a9f5`, `dfbab4538c8e66af`, `f7bdf64c50887553`, `8221b8c1988dceea` (see `tests/coverage/20260428_2310.txt`).
  - Cause: OSRM dual-base loop, HTTP exception and server-error logging (preview truncation), debug summary payload, non-array JSON skip, `osrm_no_route` vs `osrm_unreachable`, and empty-primary + fallback behavior were only exercised indirectly via `getTripInfo()`, leaving many line-level mutants under-mapped for focused runs.
  - Fix: added `invokeRequestOsrmForTripInfoCoords` reflection helper and six tests: `test_request_osrm_for_trip_info_coords_retries_fallback_base_after_primary_server_error`, `test_request_osrm_for_trip_info_coords_exception_log_truncates_long_coords_preview`, `test_request_osrm_for_trip_info_coords_server_error_log_truncates_long_body_preview`, `test_request_osrm_for_trip_info_coords_logs_debug_summary_and_returns_osrm_no_route_for_ok_without_routes`, `test_request_osrm_for_trip_info_coords_skips_non_array_json_and_returns_unreachable`, `test_request_osrm_for_trip_info_coords_skips_empty_primary_base_and_uses_fallback`.

- Cluster `TripRepository.php` **873–952** (`storeTripInfoSuccess`, `routingServiceUnavailableResponse`): e.g. `c51164942b9342ee`, `a13adcc10a2ff2b6`, `69479fd6cb8eb1c8`, `5fe8b1a88e1272b8`, `d534ed6b58996524`, `13f50b320aa39fa5`, `7da4e055bdca64b5`, `16feca025c66f5a4` (see `tests/coverage/20260428_2310.txt`).
  - Cause: success-path cache TTL (`max(3600, …)`), `RouteCache::updateOrCreate` payload, both `Log::info` branches (cached vs BYPASS), pricing arithmetic + sellado ternary, and the routing-unavailable helper array were weakly tied to tests at the line level.
  - Fix: added `invokeStoreTripInfoSuccess` / `invokeRoutingServiceUnavailableResponse` helpers and tests `test_store_trip_info_success_caches_route_and_logs_ttl_when_cache_enabled`, `test_store_trip_info_success_clamps_ttl_to_minimum_3600_seconds`, `test_store_trip_info_success_skips_cache_write_and_logs_bypass_when_cache_bypass_enabled`, `test_store_trip_info_success_computes_price_cents_tolls_sellado_and_max_cap`, `test_routing_service_unavailable_response_returns_translated_error_payload`.

- `e64f2d875a0b1026` (`Line 978: RemoveMethodCall` on `filterActiveTrips` in `hideTrips`), plus tail helpers `getTripByTripPassenger` / `selladoViaje`, and stricter `routingServiceUnavailableResponse` shape (`948–952` RemoveArrayItem / AlwaysReturnEmptyArray cluster in stale report).
  - Cause: `hideTrips` had no assertion that inactive (past, non-weekly) rows stay untouched while weekly-or-future rows receive the sentinel `deleted_at`; `selladoViaje` had no case with `module_trip_creation_payment_enabled=false`; passenger lookup had no explicit null case; routing-unavailable test used field-wise asserts only.
  - Fix: added `test_hide_trips_only_soft_deletes_active_scope_trips_via_filter_active_trips`, `test_get_trip_by_trip_passenger_returns_null_when_id_not_found`, `test_sellado_viaje_reflects_payment_module_disabled`, and tightened `test_routing_service_unavailable_response_returns_translated_error_payload` to `assertEquals` the full expected payload array.

- `697e7b3ac4ecb099`, `1abc2e931491d83c` (`shareTrip`, lines ~642–645 in `tests/coverage/20260428_2310.txt`).
  - Cause: `shareTrip` only had a happy-path / wrong-user check; dropping `filterActiveTrips` could still return true when the only matching trip was an expired one-off, and the weekly “never expires” branch was not asserted for a past `trip_date`.
  - Fix: added `test_share_trip_returns_false_when_only_match_is_expired_non_weekly_trip` and `test_share_trip_returns_true_for_weekly_schedule_trip_even_when_trip_date_is_past`.

- Cluster `getTripInfo()` **cache HIT** (`TripRepository.php` ~683–702, UNCOVERED in `20260428_2310.txt`): e.g. `a33a8d54aa9eef26`, `269d6beb0aa03fa0`, `902589d676dfcf05`, `c0160af1be00eac3`, `e8647b8a3ea11433`.
  - Cause: cache short-circuit returned `route_data` but the `Log::info('[trip_route|getTripInfo] cache HIT', …)` payload (null `expires_at`, nested `??` metrics, `route_cache_id`) and “no HTTP when cached” were not asserted.
  - Fix: added `test_get_trip_info_cache_hit_logs_null_expires_and_sparse_route_data_fields` and `test_get_trip_info_cache_hit_logs_iso8601_expires_and_numeric_cached_metrics` with `Log::spy()` + `Http::assertNothingSent()`.

- `getTripInfo()` **expired RouteCache** (`TripRepository.php` ~683–687 `orWhere('expires_at', '>', now())`) and `simplePrice` zero-distance boundary (`~661–663`).
  - Cause: no test proved a past `expires_at` row is ignored (would wrongly return stale `route_data` if the inner OR / comparator mutates); `simplePrice` only covered a positive distance.
  - Fix: added `test_get_trip_info_skips_expired_route_cache_and_logs_cache_miss_before_osrm` (stale row + OSRM success + `cache MISS` log + `Http::recorded()`) and `test_simple_price_returns_zero_for_zero_distance`.

- `TripRepository::addPoints` / `deletePoints` (`~616–658`): e.g. `f715fdf844c80168` (catch empty address) adjacent branches in report; explicit `address` and string `json_address` paths were only exercised via array `json_address` in factories.
  - Cause: `isset($point['address'])` short-circuit, `json_decode` string branch for `ciudad`, and `$trip->points()->delete()` wiping `trips_points` had no direct assertions beyond the combined add/generate/delete flow.
  - Fix: added `test_add_points_uses_explicit_address_when_provided`, `test_add_points_reads_ciudad_from_json_encoded_string_json_address`, and `test_delete_points_removes_all_rows_for_trip_in_trips_points`.

- `TripRepository::addPoints` **error handling** (`~623–631`): `f715fdf844c80168` and PHP 8 `Error` paths (invalid JSON → `null->ciudad`, missing `ciudad` in array).
  - Cause: `catch (Exception $ex)` never caught typical PHP 8 failures (`Error` / undefined array key), so the empty-string fallback was effectively untestable and brittle in production.
  - Fix: broadened catch to `catch (\Throwable $ex)` and added `test_add_points_sets_empty_address_when_json_string_is_invalid` plus `test_add_points_sets_empty_address_when_array_json_address_omits_ciudad`.

- `TripRepository::unhideTrips` (`~982–987`): future `trip_date`, sentinel `deleted_at`, and `update()` return value.
  - Cause: only the happy hide→unhide path was covered; removing `trip_date` / exact `deleted_at` filters or ignoring `update()`’s affected-row count could regress silently.
  - Fix: added `test_unhide_trips_does_not_restore_when_trip_date_is_before_now`, `test_unhide_trips_does_not_restore_regular_soft_delete_without_sentinel_timestamp`, and `test_unhide_trips_returns_number_of_restored_sentinel_hidden_trips`.

## RatingRepository

- Cluster `RatingRepository.php` (`tests/coverage/20260428_2310.txt` ~1278–1334): `getRating` chained filters (`user_id_to`, `trip_id`), `getRatings` ordering, `getPendingRatings` voted/eager-load constraints, and create payload defaults.
  - Cause: `getRating` had only a direct-hit case with no distractors; removing extra `where(...)` calls could still pass. `getRatings` did not assert descending `created_at`. Pending ratings did not include a same-user `voted=true` row or relation-loaded checks for `with(['from','to','trip'])`. `create` asserted only a subset of defaults, leaving empty-string/null payload items weakly guarded.
  - Fix: strengthened `test_get_rating_returns_row_for_from_to_trip` with mismatching noise rows; added `test_get_ratings_orders_by_created_at_desc`; strengthened `test_get_pending_ratings_filters_by_user_voted_and_recency` with a voted row and `relationLoaded` assertions; extended `test_create_persists_pending_rating_shape` with `comment`, `reply_comment`, `reply_comment_created_at`, and `rate_at` default assertions.

- `getRatings` pagination via `make_pagination` (`RatingRepository.php` ~40–43): returning `paginate($pageSize)` vs bare `get()` when `page_size` set.
  - Cause: `test_get_ratings_filters_available_and_value_when_page_size_null` intentionally kept `$pageSize == null`; omitting `paginate()` could survive without a `LengthAwarePaginator` contract check.
  - Fix: added `test_get_ratings_paginates_when_page_size_provided`.

- `RatingRepository.php` `find` (`~70–73`): `RatingModel::find($id)` when no row exists.
  - Cause: tests always resolved valid ids; replacing `find` with `first()` or dropping the lookup could survive without an explicit null guard.
  - Fix: added `test_find_returns_null_when_rating_id_missing`.

- `RatingRepository.php` `findBy` (`~75–78`): empty result set from `where(...)->get()`.
  - Cause: `test_find_and_find_by` only asserted a hit row; removing `where($key, $value)` or swapping `get()` could survive without an empty-query assertion.
  - Fix: added `test_find_by_returns_empty_collection_when_no_match`.

- `RatingRepository.php` `getRating` (`~11–17`): `first()` when no row matches all three keys.
  - Cause: happy path added noise rows but always included the expected triple; dropping one `where` could still pass.
  - Fix: added `test_get_rating_returns_null_when_no_row_matches_triple`.

- `RatingRepository.php` `update` (`~102–104`): `return $rateModel->save()`.
  - Cause: only successful saves were asserted.
  - Fix: added `test_update_returns_false_when_save_fails`.

- `RatingRepository.php` `update`: successful-path `save()` invoke.
  - Cause: integration + false-path tests left successful `save()` droppable without a mock expectation.
  - Fix: added `test_update_invokes_save`.

- `RatingRepository.php` `getPendingRatings` (`~60–67`): empty collection when user has no pending recent rows.
  - Cause: listing tests always seeded at least one qualifying row.
  - Fix: added `test_get_pending_ratings_returns_empty_when_user_has_no_candidates`.

- `RatingRepository.php` `getRatings` (`~20–43`): zero rows for `user_id_to` / `available`, including paginated branch.
  - Cause: filters always asserted non-empty lists.
  - Fix: added `test_get_ratings_returns_empty_when_no_available_ratings_for_user` and `test_get_ratings_paginates_empty_when_no_rows_match`.

## FileRepository

- Cluster `FileRepository.php` (`tests/coverage/20260428_2310.txt` ~1350+): `createFromFile` directory creation flags and `createFromData` generated-name branch (`$name` null).
  - Cause: file-move happy path covered only a shallow folder; recursive `makeDirectory(..., 0777, true, true)` mutations can survive unless nested missing folders are asserted. Data-upload test pinned only named-file path, leaving the autogenerated filename branch (`microtime`/`date` + extension) under-asserted when `$name` is null.
  - Fix: added `test_create_from_file_creates_nested_folder_recursively` and `test_create_from_data_generates_filename_when_name_is_null` with existence and extension assertions for generated output.

- `FileRepository.php` `delete` (`~85–88`): missing file path under normalized folder.
  - Cause: tests only deleted existing files; idempotent delete behavior was not asserted.
  - Fix: added `test_delete_does_not_throw_when_file_missing`.

## ReferencesRepository

- `ReferencesRepository.php` `create` (`~9–11`): `return $reference->save()`.
  - Cause: integration tests only asserted successful persists; removing the return or ignoring `save()`’s boolean could survive without a false-path assertion.
  - Fix: added `test_create_returns_false_when_save_fails` (mocked `References` with `save` → `false`).

- `ReferencesRepository.php` `create` (`~9–11`): must invoke `$reference->save()` on success path.
  - Cause: DB-backed tests persist via `save()` but did not assert an explicit `save()` expectation on the model instance.
  - Fix: added `test_create_invokes_save` (`save()` → `true` mock).

## NotificationRepository

- `NotificationRepository.php` `getNotifications` signature/default and pagination gate (`tests/coverage/20260428_2310.txt` ~1439–1445): `FalseToTrue` on `$unread = false` and `BooleanAndToBooleanOr` on `$page_size && $page`.
  - Cause: tests always passed `$unread` explicitly and only exercised pagination when both values existed; mutating the default to true or the gate from AND to OR could still pass.
  - Fix: added `test_get_notifications_default_argument_uses_all_notifications_not_unread_only` and `test_get_notifications_does_not_paginate_when_only_page_size_or_page_is_provided`.

- `NotificationRepository.php` `getNotifications`: user with zero notification rows.
  - Cause: listings always inserted rows before `get`; empty relation could regress without an assertion.
  - Fix: added `test_get_notifications_returns_empty_when_user_has_none`.

- `NotificationRepository.php` `find` (`~39–41`): `first()` when no row matches `id`.
  - Cause: tests covered another user’s notification id but not a non-existent id for the same user.
  - Fix: added `test_find_returns_null_when_notification_id_does_not_exist`.

- `NotificationRepository.php` `markAsRead` (`~23–31`): `$notification` branch must call `$notification->save()` after assigning `read_at`.
  - Cause: integration tests asserted DB state only on real rows; dropping `save()` could survive without a mock expectation.
  - Fix: added `test_mark_as_read_invokes_save_when_notification_provided`.

- `NotificationRepository.php` `delete` (`~33–37`): must call `$notification->save()` after assigning `deleted_at`.
  - Cause: integration tests asserted persisted timestamps only; omitting `save()` could survive without a mock expectation.
  - Fix: added `test_delete_invokes_save_after_setting_deleted_at`.

## CarsRepository

- `CarsRepository.php` `index` (`~30–33`): `$user->cars` when the user has zero vehicle rows.
  - Cause: tests always created a car before `index`; empty relation could regress without an assertion (similar to `DeviceRepository::getDevices`).
  - Fix: added `test_index_returns_empty_when_user_has_no_cars`.

- `CarsRepository.php` `create` / `update` (`~10–17`): `return $car->save()`.
  - Cause: integration tests only asserted successful persists; ignoring `save()`’s boolean could survive without a false-path assertion.
  - Fix: added `test_create_returns_false_when_save_fails` and `test_update_returns_false_when_save_fails`.

- `CarsRepository.php` `delete` (`~25–27`): `return $car->delete()`.
  - Cause: integration tests only asserted successful soft/removal paths on real rows.
  - Fix: added `test_delete_returns_false_when_delete_fails`.

- `CarsRepository.php` `create` / `update` (`~10–18`): must invoke `$car->save()` on success path.
  - Cause: false-path mocks existed; explicit successful `save()` expectations were missing for RemoveMethodCall clusters.
  - Fix: added `test_create_invokes_save` and `test_update_invokes_save`.

- `CarsRepository.php` `delete`: successful-path `delete()` invoke.
  - Cause: only false-path and integration deletes were covered.
  - Fix: added `test_delete_invokes_delete`.

## PhoneVerificationRepository

- `PhoneVerificationRepository.php` `find` (`~28–31`): `PhoneVerification::find($id)` when no row exists.
  - Cause: CRUD test only exercised `find` on a persisted id after create.
  - Fix: added `test_find_returns_null_when_phone_verification_missing`.

- `PhoneVerificationRepository.php` `create` / `update` (`~12–22`): `return $phoneVerification->save()`.
  - Cause: integration tests only asserted successful persists.
  - Fix: added `test_create_returns_false_when_save_fails` and `test_update_returns_false_when_save_fails`.

- `PhoneVerificationRepository.php` `create` / `update`: must invoke `$phoneVerification->save()` on truthy path.
  - Cause: false-path-only mocks left successful `save()` removable without an expectation conflict.
  - Fix: added `test_create_invokes_save` and `test_update_invokes_save`.

- `PhoneVerificationRepository.php` `delete` (`~78–81`): `return $phoneVerification->delete()`.
  - Cause: round-trip test only asserted truthy delete on real rows.
  - Fix: added `test_delete_returns_false_when_delete_fails`.

- `PhoneVerificationRepository.php` `delete`: successful-path `delete()` invoke.
  - Cause: false-path-only mock left successful `delete()` removable without expectation conflict.
  - Fix: added `test_delete_invokes_delete`.

- `PhoneVerificationRepository.php` `getLatestUnverifiedByUser` (`~36–41`): no rows with `verified = false`.
  - Cause: tests always seeded pending rows; empty branch was untested.
  - Fix: added `test_get_latest_unverified_by_user_returns_null_when_no_unverified_rows`.

- `PhoneVerificationRepository.php` `getLatestByUser` (`~47–51`): user with zero verification rows.
  - Cause: ordering tests always created history first.
  - Fix: added `test_get_latest_by_user_returns_null_when_user_has_none`.

- `PhoneVerificationRepository.php` `getByUser` (`~68–72`): empty ordered collection.
  - Cause: listing tests always inserted rows.
  - Fix: added `test_get_by_user_returns_empty_when_none`.

- `PhoneVerificationRepository.php` `getVerificationStats` (`~86–94`): `WHERE user_id` matches zero rows (SQL still returns one aggregate row with zeros/null SUMs).
  - Cause: stats test only covered multi-row aggregates.
  - Fix: added `test_get_verification_stats_returns_zero_totals_when_user_has_no_attempts`.

- `PhoneVerificationRepository.php` `isPhoneVerifiedByAnotherUser` (`~57–62`): no row for `phone_number`, or rows exist but none with `verified = true`.
  - Cause: conflict test always seeded a verified row before asserting lookup (excluding owner vs stranger).
  - Fix: added `test_is_phone_verified_by_another_user_returns_null_when_phone_unknown` and `test_is_phone_verified_by_another_user_returns_null_when_only_unverified_rows_exist`.

## DeviceRepository

- `DeviceRepository.php` `getDeviceBy` (`~30–33`): `Device::where($key, $value)->first()` when no row matches.
  - Cause: happy path asserted only a hit row; empty lookup could regress without a null assertion.
  - Fix: added `test_get_device_by_returns_null_when_no_row_matches`.

- `DeviceRepository.php` `getDevices` (`~25–28`): user with zero device rows.
  - Cause: tests always seeded devices before listing; dropping `where('user_id', …)` or swapping `get()` could survive without an empty-collection assertion.
  - Fix: added `test_get_devices_returns_empty_when_user_has_no_devices`.

- `DeviceRepository.php` `deleteDevices` (`~35–37`): zero rows deleted when user has no devices.
  - Cause: happy path only asserted delete count ≥ 2; omitting `where('user_id', …)` could still pass.
  - Fix: added `test_delete_devices_returns_zero_when_user_has_no_devices`.

- `DeviceRepository.php` `store` / `update` (`~10–22`): `return $device->save()`.
  - Cause: integration tests only asserted successful persists.
  - Fix: added `test_store_returns_false_when_save_fails` and `test_update_returns_false_when_save_fails`.

- `DeviceRepository.php` `store` / `update`: successful-path `save()` invoke.
  - Cause: false-path mocks existed without complementary successful `save()` expectations.
  - Fix: added `test_store_invokes_save` and `test_update_invokes_save`.

- `DeviceRepository.php` `delete` (`~15–18`): must invoke `$device->delete()` (void method — forwarding-only contract).
  - Cause: removal tests used persisted rows only; omitting the call could survive without a mock expectation.
  - Fix: added `test_delete_invokes_device_delete`.

## UserRepository

- `UserRepository.php` `getUserBy` (`~68–71`): `User::where($key, $value)->first()` when no row matches.
  - Cause: only happy-path email lookup was asserted.
  - Fix: added `test_get_user_by_returns_null_when_no_match`.

- `UserRepository.php` `show` absent-user path (`~41–45`): `first()` may return null; `private_note` stripping must stay behind `if ($user)`.
  - Cause: tests always loaded an existing id, so skipping the reset body could mutate without failing.
  - Fix: added `test_show_returns_null_when_user_not_found`.

- `UserRepository.php` `addFriend` pivot payload (`~131–136`): both `attach` calls carry `origin` + `state`; mutations could drop columns or change `$provider` handling.
  - Cause: friendship existence was asserted via relations only; pivot `origin` was never checked against the `$provider` argument.
  - Fix: strengthened `test_add_friend_and_delete_friend_sync_bidirectional_pivot` with `assertDatabaseHas('friends', …)` for both directions.

- `UserRepository.php` password-reset lookups (`getUserByResetToken` ~162–167, `getLastPasswordReset` ~170–175): missing-token / unknown-email paths.
  - Cause: happy-path round-trip asserted resolution only; removing the `if ($pr)` guard or returning a dummy model could survive without explicit absence checks.
  - Fix: added `test_password_reset_token_lookups_return_null_when_missing`.

- `UserRepository.php` `deleteResetToken` (`~157–159`): `delete()` affecting zero rows.
  - Cause: tests always deleted an inserted reset row first.
  - Fix: added `test_delete_reset_token_leaves_table_unchanged_when_no_rows_match`.

- `UserRepository.php` `getUserByResetToken` (`~164–167`): reset row exists but **`users`** has no row with that email (orphaned reset row).
  - Cause: resolution checks assumed every `password_resets` email mapped to an existing user.
  - Fix: added `test_get_user_by_reset_token_returns_null_when_email_missing_from_users`.

- `UserRepository.php` (`tests/coverage/20260428_2310.txt` ~1464–1474): `show()` eager-load list (`accounts`, `donations`, `referencesReceived`, `cars`) and `acceptTerms`/`updatePhoto` return values (`AlwaysReturnNull`).
  - Cause: `show` test only checked `private_note` nulling, so dropping relation keys from `with([...])` could survive. `acceptTerms`/`updatePhoto` effects were asserted via fresh model state, but method return contracts were not, allowing null-return mutations.
  - Fix: strengthened `test_show_nulls_private_note_and_loads_relations` with `relationLoaded` assertions for all four relations; strengthened `test_accept_terms_and_update_photo` with non-null return and identity (`$user->id`) checks for both methods.

- `UserRepository.php` `searchUsers` (`~73–89`): truthy `$name` but every OR predicate misses.
  - Cause: limit/order tests always asserted ≥1 hit when `$name` was non-empty.
  - Fix: added `test_search_users_returns_empty_collection_when_no_matches`.

- `UserRepository.php` `index` (`~92–111`): zero candidates after self-exclusion when no other rows exist.
  - Cause: index tests always seeded additional strangers.
  - Fix: added `test_index_returns_empty_when_only_self_exists`.

- `UserRepository.php` `getNotifications` (`~178–185`): empty `notifications` / `unreadNotifications` relations (thin delegate).
  - Cause: notification tests always inserted rows before listing.
  - Fix: added `test_get_notifications_returns_empty_when_user_has_none`.

- `UserRepository.php` `unansweredConversationOrRequestsByTrip` (`~192–210`): both aggregates zero (no pending requests on trip; no unanswered-by-driver conversations).
  - Cause: aggregate test always asserted positive counts first.
  - Fix: added `test_unanswered_conversation_or_requests_by_trip_returns_zero_when_none`.

- `UserRepository.php` `acceptTerms` / `updatePhoto` (`~52–66`): must call `$user->save()` after mutating attributes.
  - Cause: integration tests asserted persisted columns but not an explicit `save()` expectation on the model instance.
  - Fix: added `test_accept_terms_invokes_save` and `test_update_photo_invokes_save` (`mock(User::class)->makePartial()` + `save()` expectation).

- `UserRepository.php` `markNotification` (`~187–190`): thin delegate must call `$notification->readed()`.
  - Cause: integration tests asserted DB/read_at side effects only on real notifications.
  - Fix: added `test_mark_notification_invokes_readed` (`shouldReceive('readed')->once()` on a `DatabaseNotification` mock).

## RoutesRepository

- `RoutesRepository.php` (`tests/coverage/20260428_2310.txt` ~1122–1152): `getPotentialsNodes` lng max/min branch (`if ($n1->lng > $n2->lng)`), `autocomplete` log concat (`$name.' '.$country`), and `whereRaw(CONCAT(name, state, country) like ?)` filtering.
  - Cause: bounding-box test primarily exercised one ordering shape; reversed-lng branch comparators were under-asserted. Autocomplete tests matched mostly by name and country, so state/country concat and exact log message construction could mutate without failure.
  - Fix: added `test_get_potentials_nodes_handles_reversed_lng_order_and_keeps_bounds` and `test_autocomplete_matches_state_country_concat_and_logs_query_context` (state-token search + `Log::shouldReceive('info')->with($needle.' AR')`).

- `RoutesRepository.php` `getPotentialsNodes` (`~19–25`): lat max/min branch when `$n1->lat < $n2->lat` (else path vs northern-first ordering).
  - Cause: bbox integration tests mostly lat-descended endpoints (`n1` north of `n2`), so swapping lat assignment without an assertion near the expanded lat floor could survive.
  - Fix: added `test_get_potentials_nodes_handles_reversed_lat_order_and_keeps_bounds` (southern endpoint first + node just inside vs clearly outside `minLat - latDiff`).

- `RoutesRepository.php` `autocomplete` (`~41–55`): zero-row result set for `CONCAT(...) LIKE` + country filter.
  - Cause: every autocomplete test asserted at least one hit; removing `whereRaw`, `where('country', …)`, `orderBy`, or `limit` could survive without an empty-collection assertion.
  - Fix: added `test_autocomplete_returns_empty_when_no_rows_match`.

- `RoutesRepository.php` `saveRoute` (`~58–67`): must call `$route->nodes()->sync($nodeIds)` then `$route->save()` after setting `processed`.
  - Cause: integration tests asserted pivots + DB `processed` only on persisted routes.
  - Fix: added `test_save_route_invokes_sync_then_save` (partial `Route` mock + `sync`/`save` expectations).

## UserRepository (searchUsers follow-up)

- `UserRepository.php` `searchUsers` (`tests/coverage/20260428_2310.txt` stale cluster ~1477–1527): OR filters on `name` / `email` / `nro_doc` / `mobile_phone`, plus `with(['accounts','cars'])` and `orderBy('name')`.
  - Cause: prior test used one mixed token and membership-style assertions, which could let some concat/OR variants survive. Relation eager-loading and strict alphabetical ordering were not asserted directly.
  - Fix: strengthened `test_search_users_matches_name_email_doc_or_phone` with strict per-field searches and one-result expectations; added `test_search_users_orders_by_name_and_eager_loads_accounts_and_cars`.

## UserRepository (`index` map branch)

- `UserRepository.php` `index` tail (`~113–126`): `allFriends()` pivot `state` branching into `$item->state` (`request` vs `friend` vs `none`).
  - Cause: existing index tests validated exclusions/search for `state === 'none'` and accepted friend absence, but never asserted the **`FRIEND_REQUEST` → `'request'`** decoration path.
  - Fix: added `test_index_sets_state_request_when_pending_friend_edge_exists` using `FriendsRepository::add($self, $peer, FRIEND_REQUEST)` (row must be uid1→uid2 from `$user` so `allFriends()` sees it) plus a name-scoped `index` query.
  - Follow-up: **`FRIEND_ACCEPTED` → `'friend'`** (else branch when `$u` exists) was never asserted because full `addFriend` sync excludes peers from `index`; **`FriendsRepository::add($self, $peer, FRIEND_ACCEPTED)`** alone keeps the peer visible while `$user->allFriends()` still reports ACCEPTED.
  - Fix: added `test_index_sets_state_friend_when_accepted_edge_uid1_only`.

## SubscriptionsRepository (getPotentialNode follow-up)

- `SubscriptionsRepository.php` line ~169 (`getPotentialNode`): `whereBetween('lng', ...)` before `first()` result selection.
  - Cause: prior bbox tests allowed multiple in-lat-range nodes and accepted several ids, so dropping the lng filter could still return an allowed row and pass nondeterministically.
  - Fix: added `test_get_potential_node_requires_lng_where_between_before_first_result` using non-persisted bbox endpoints plus controlled insert order (`outsideFirst` then `insideSecond`) to ensure removing lng filtering changes the selected first row.

## SocialRepository

- `SocialRepository.php` `create` provider resolution (`tests/coverage/20260428_2310.txt` ~43–45): `if (is_null($provider)) { $provider = $this->provider; }`.
  - Cause: tests only covered default-provider creation (`null` third argument); passing an explicit provider could regress without asserting persisted `provider`.
  - Fix: added `test_create_respects_explicit_provider_over_repository_default`.

- `SocialRepository.php` `find` (`~29–38`): no row for `(provider, provider_user_id)` under repository default provider.
  - Cause: `test_find_with_explicit_provider_overrides_default` covered wrong-provider mismatch; an unknown subject id with an otherwise-correct default provider had no dedicated empty-lookup assertion.
  - Fix: added `test_find_returns_null_when_no_account_matches_subject`.

- `SocialRepository.php` `get` (`~59–67`): empty `accounts()` relation or `where('provider', …)` miss.
  - Cause: `test_get_returns_all_accounts_or_filters_by_provider` always asserted non-empty collections for both branches.
  - Fix: added `test_get_returns_empty_when_user_has_no_accounts` and `test_get_returns_empty_when_provider_filter_matches_no_rows`.

- `SocialRepository.php` `delete` (`~54–57`): must invoke `$account->delete()`.
  - Cause: removal tests used real models only; dropping the call could survive without a mock expectation.
  - Fix: added `test_delete_invokes_social_account_delete`.

## AuthController (`app/Http/Controllers/Api/v1/AuthController.php`)

- `dcf236f763f5aae1` (`Line 265: AlwaysReturnNull`, report `tests/coverage/20260428_2310.txt` ~44266)
  - Cause: `log()` always returned `true`, but no test asserted the return value, so replacing the body with `return null` still left the suite green.
  - Fix: `Tests\Unit\Http\Controllers\Api\v1\AuthControllerTest::test_log_returns_true` resolves the controller from the container and asserts the return value is truthy.

## SubscriptionController (`app/Http/Controllers/Api/v1/SubscriptionController.php`)

- Constructor `logged` middleware plus `create` / `update` / `delete` / `show` / `index` JSON envelopes (report ~44277–44378; e.g. `5d29aa8234bb5792` `RemoveMethodCall`, `0210d42bec609bd0` / `3d136cad6b2c42dd` on `Line 30`, `d7f5005e2e783a35` / `889a361f5f558540` on `Line 42`, `37d088fcab7e2292` / `9f81ffbda5a22c2f` on `Line 53`, `84645031db77ee06` / `b736079953f89cd7` on `Line 72`, and branch mutants on the `if (! $model)` / `if (! $result)` guards).
  - Cause: `Tests\Feature\Http\SubscriptionsApiTest` mocked `SubscriptionsManager`, so the controller still ran but responses were not tied to real persistence; weak assertions (`status == 200` only) let `RemoveArrayItem`, `AlwaysReturnNull`, and negated-condition mutants survive.
  - Fix: rewrote that feature file to use real auth + real manager/DB: `assertUnauthorized` for guests (kills dropped `middleware('logged')`), `assertExactJson` / `assertJsonPath` / `assertJsonStructure` on success payloads, `assertUnprocessable` on validation failure, and DB assertions for create/delete.

## `SubscriptionsManager` (`app/Services/Logic/SubscriptionsManager.php`)

- **Validation and update-error propagation contracts** (`validator()` rules and `update()` validation-fail path; report `RUN` ~7981, cluster around lines 23/29/31/32 and 116).
  - Cause: tests did not pin non-string `from_address` / `to_address` rejection or the update branch that forwards validator errors when payload is invalid, so rule-removal and `setErrors()`-removal mutants could survive.
  - Fix: added `test_validator_rejects_non_string_addresses` and `test_update_returns_null_and_sets_validation_errors_when_payload_is_invalid` to assert stable validation behavior and error propagation.
  - Mutant IDs: `7079be4265b9da71`, `88811053b8cd90e8`, `7d261d60c73cc95a`, `746c9b7a522a2141`, `0d3468a50523002f`.

- **Duplicate/ownership/delete branch behavior** (`create()` duplicate matching including empty optional fields, `show()` owner check, and `delete()` failure branches; report cluster around lines 59–94, 135, 151, 156).
  - Cause: previous tests mainly covered full-geometry duplicates and successful delete, but did not constrain duplicate detection when optional date/coords are empty, scalar-equivalent owner ids, or repository-delete-failure and non-owner delete paths.
  - Fix: added tests for duplicate rejection with empty optional geometry/date, owner acceptance with equivalent scalar ids (`string`/`int`), `model_not_found` on non-owner delete, and `cant_delete_model` when repository `delete` returns false.
  - Mutant IDs: `adfe249adfab4a15`, `d38088d2611a5bba`, `8c85ef77b044b41f`, `cdcb164e95818106`, `6b9b37a01feef1df`, `dcf6376cdd1086de`, `ab290f84bfd422fe`, `8b86db852cdfcf98`, `b357d2d567671f6e`, `dfe39af01c8fc3b0`, `912d4c066b4d3438`, `f07c24817044966d`, `d45b4dfdecbbc811`, `86253f3d47dba1c8`, `5dac330ffb095910`, `1c607efe448a33ff`, `26e1a4e11aab3650`, `71a7ddce4abc6863`, `62d54fe498c12de8`, `f585e6912cde6e63`, `86294367b1d5921e`, `3932860febfe07e3`, `f44c648d53d644c0`, `5aee697ace1e889a`, `929e73e5e9ae4e1a`, `daea7864f1de4247`, `df112dd7ff33f72c`, `eae229a29c598138`, `b7a057dd14f2f1ad`, `e5384086ab326d36`, `0ca492edc392fd1b`, `91130cb815625280`.

## `ConversationsManager` (`app/Services/Logic/ConversationsManager.php`)

- **Conversation defaults and trip-id normalization/update behavior** (`createConversation()`, `findOrCreatePrivateConversation()`, `updateTripId()`, and admin trip getter path; report `RUN` ~8080 with survivors around lines 48, 70, 92, 102, 141).
  - Cause: tests covered basic create/send flows but did not pin the default empty title, the invalid-trip-id fallback to `null`, nor the branch that updates an existing private conversation with a valid `trip_id`; admin path for `getConversationByTrip` was also not asserted.
  - Fix: added tests asserting empty title on created conversations, null trip assignment when trip does not exist, trip-id update on existing private conversation, and successful `getConversationByTrip` retrieval for admin users.
  - Mutant IDs: `450e137638727d58`, `ee97f4c53340e271`, `06b393cf1bd1bd4b`, `1428dc7499739c47`, `035873ed92193a90`, `0b749ff3c9b55d2d`.

## ManualIdentityValidationController (`app/Http/Controllers/Api/v1/ManualIdentityValidationController.php`)

- `dd32c96b7620c345` (`Line 19: RemoveMethodCall`, report ~44383)
  - Cause: nothing hit the manual-identity user routes without a token; removing `$this->middleware('logged')` could expose endpoints to guests without a failing test.
  - Fix: `ManualIdentityValidationApiTest::test_manual_identity_cost_and_status_require_authentication` asserts `401` + `Unauthorized.` on both `GET api/users/manual-identity-validation-cost` and `GET api/users/manual-identity-validation` when unauthenticated. `test_manual_identity_preference_and_qr_order_require_authentication` does the same for `POST api/users/manual-identity-validation/preference` and `POST api/users/manual-identity-validation/qr-order`. `test_manual_identity_submit_requires_authentication` covers `POST api/users/manual-identity-validation` without a token.

- **Cost endpoint** `cost()` lines ~27–35 (report ~44395–44599; includes e.g. `436487b92f5c0e6c` `IfNegated`, `87488e9f0331ec65` `FalseToTrue`, `bac665cff310dd2e` `RemoveNot`, `53ee18c2f699605f` / `8e047db978a94613` `DecrementInteger`/`IncrementInteger` on `cost_cents`, `a80290c9e2c5db24` `RemoveArrayItem`, `d3d3e7d3ceae9c1e` `RemoveEarlyReturn`, and the parallel cluster on the manual-enabled guard through `b8b6e851760e3066` `AlwaysReturnNull` on the final return).
  - Cause: only `submit()` was covered; `cost()` config gates and exact JSON bodies were never executed under test, so mutants on `config()` checks, early returns, and `['cost_cents' => …]` survived as UNCOVERED.
  - Fix: `test_cost_returns_zero_when_identity_validation_disabled`, `test_cost_returns_zero_when_manual_validation_disabled`, and `test_cost_returns_configured_amount_when_manual_flow_enabled` drive the three outcomes and use `assertExactJson(['cost_cents' => …])` so wrong branches, wrong integers, stripped keys, or `null` responses fail.

- **Status endpoint** disabled / empty payload (`status()` lines ~43–68; report ~44611–44680+, e.g. `25095fd113b85a30` `IfNegated`, `bf7c63de56fd7efc` `FalseToTrue`, `4820bc3b581b8231` `RemoveNot`, `da49fea0f768fefa` `RemoveEarlyReturn`, `59ebab625a619a16` / `ed9e8bd5caed1773` / `bbd0c0fc13c12029` / `25b4dfd0df447673` / … `RemoveArrayItem` on the empty-state JSON, plus `AlwaysReturnNull` variants on the same returns).
  - Cause: no HTTP test asserted the “feature off” or “no submission” JSON contract; mutants could flip conditions, drop keys, or return `null` without detection.
  - Fix: `expectedEmptyStatusPayload()` mirrors the public API shape; `test_status_returns_empty_contract_when_identity_validation_disabled` and `test_status_returns_empty_contract_when_user_has_no_submission` assert that exact payload.

- **Status endpoint** populated branch (`status()` ~71–78; report ~44887–45013; e.g. `f058e10e2fb33a35` `AlwaysReturnNull`, `ff2b39528bced7dc` `TrueToFalse`, `1682fd7b2855ba66` / `b3bedf265d6fdd33` `TernaryNegated`, `RemoveArrayItem` IDs `b739e26ca54d1c54`, `891b5c63de8a3857`, `0b26c4b12645c8c0`, `c11a90fd3b6ca4e1`, `7a14d59e82bfdd0b`, `677e79258d60d3e2`, `80c9654760e4bb2c`).
  - Cause: field-by-field `assertJsonPath` still let mutants remove keys, flip `has_submission`, negate timestamp ternaries, or return `null` from the action without failing.
  - Fix: `expectedPopulatedStatusPayload()` rebuilds the observable JSON from the persisted row; `test_status_returns_latest_submission_summary` uses `assertExactJson` for the paid path and `travelTo` so two rows get distinct `created_at` values (status picks `orderBy('created_at', 'desc')->first()`); `test_status_serializes_null_timestamps_for_unpaid_submission` pins `paid_at` / `submitted_at` / `review_note` as JSON `null` when unset.

- **createPreference** (`createPreference()` ~88–133; report from ~45019, e.g. `f8100c66cf3468e9` `IfNegated`, `955407cf1ca72033` `FalseToTrue`, `52aa470676520d86` `RemoveNot`, parallel clusters on the manual-enabled and `costCents <= 0` guards, `FalseToTrue` / `RemoveNot` on the “reuse unpaid row” query, `RemoveArrayItem` on create payload, try/catch rollback, `init_point` / `sandbox_init_point` null-coalescing ~122, failure when both URLs missing, and the success JSON ~130–132).
  - Cause: Mercado Pago preference creation was never exercised under tests, so config gates, comparisons, persistence, URL fallback, rollback, and response keys stayed UNCOVERED.
  - Fix: bind a test `MercadoPagoService` mock returning `MercadoPago\Resources\Preference` instances (typed return); `test_preference_returns_unprocessable_when_identity_validation_disabled`, `…_manual_validation_disabled`, `…_cost_not_positive`; `test_preference_returns_checkout_url_and_request_id`; `test_preference_falls_back_to_sandbox_checkout_url`; `test_preference_rolls_back_new_row_when_checkout_urls_missing`; `test_preference_rolls_back_new_row_when_mercado_pago_throws`. `test_preference_reuses_existing_unpaid_request_instead_of_creating_a_second_row` pins the unpaid-row reuse branch (`where('paid', false)->orderBy('created_at', 'desc')->first()` before create; report e.g. ~45223 `3c2248073c50acb2` `FalseToTrue` on that reuse `if`).

- **createQrOrder** (`createQrOrder()` ~141–195; report continues after preference block; includes QR feature flag ~150, POS external id ~153–155, `costCents <= 0` ~157–159, reuse/create unpaid row, `empty($result['qr_data'])` rollback, and success JSON ~191–195).
  - Cause: same as preference—no HTTP tests touched QR order wiring, so feature toggles and response contract mutants survived.
  - Fix: `test_qr_order_returns_unprocessable_when_qr_flow_disabled`, `test_qr_order_returns_unprocessable_when_pos_external_id_missing`, `test_qr_order_returns_unprocessable_when_cost_not_positive`, `test_qr_order_returns_payload_from_payment_provider` (mock `createQrOrderForManualValidation` return shape), `test_qr_order_rolls_back_new_row_when_qr_data_missing`. `test_qr_order_reuses_existing_unpaid_request_instead_of_creating_a_second_row` mirrors the preference reuse contract for the QR unpaid branch (report e.g. ~45799 `4e1be1c0e6c5f234` `FalseToTrue` on the parallel reuse `if`).

- **`submit()` request ownership, payment gate, and three uploads** (`submit()` ~205–231; report ~45991–46147, e.g. `c58b42c2f5700b79` / `98bf8130ab7a594b` `RemoveArrayItem` on the `request_id` errors payload for `Line 206`, `da8d753deb40f417` / `2d4e00ceb88a5050` on `Line 222` `paid` guard, `ec303c82f94ed66b` / `ad60a93ef0c48ad4` `BooleanOrToBooleanAnd` on `Line 229` `if (! $front || ! $back || ! $selfie)`).
  - Cause: happy-path multipart tests did not assert missing `request_id`, unknown ids, another user’s `request_id`, unpaid rows, or a missing third file—so negated `if (! $requestId)`, inverted `where('user_id', …)` / `paid`, or broken `||` chains could survive mutation.
  - Fix: `test_submit_without_request_id_returns_unprocessable`, `test_submit_with_unknown_request_id_returns_unprocessable_invalid_request`, `test_submit_with_request_id_owned_by_another_user_returns_unprocessable_invalid_request`, `test_submit_when_not_paid_returns_unprocessable`, and `test_submit_with_missing_selfie_returns_unprocessable` assert stable JSON messages and that `submitted_at` stays null when the request is rejected. **Note:** `ExceptionWithErrors::render()` maps any exception carrying a non-null `$errors` array (including `[]`) to HTTP `422`, so “invalid request” is asserted as `422` + `Invalid request.` even though the controller passes `404` into the exception constructor—tests follow the observable API contract.

- **Follow-up:** `storeIdentityImage()` private HEIC path (~263–274) and any remaining `submit` mutants tied only to `ImageUploadValidator` / converter internals in `tests/coverage/20260428_2310.txt` after the blocks above.

## WhatsAppWebhookController (`app/Http/Controllers/Api/v1/WhatsAppWebhookController.php`)

- **`handle()` method dispatch** (`handle()` ~26–36; report ~46498–46570, e.g. `1e5e78cdd24ec709` `IfNegated`, `ebe3a073ed645068` `IdenticalToNotIdentical` on `POST`, `aaa1b89e65682d70` `RemoveEarlyReturn`, `a9e967a8ff399c5b` / `f928ee2d6a01c23e` / `d2e72a6b102674f1` / `7566fb0fd6d5d983` on the `405` JSON).
  - Cause: only a shallow smoke hit `GET /webhooks/whatsapp` with a bad token; nothing exercised `POST`, successful `GET` verification, or non-GET/POST verbs, so flipped method checks or a removed `405` body stayed green.
  - Fix: `Tests\Feature\Http\WhatsAppWebhookTest` calls `DELETE /webhooks/whatsapp` and asserts `405` + exact `{"error":"Method not allowed"}`; covers `POST` and successful `GET` verification paths below.

- **Webhook verification** (`handleVerification()` ~55–70; report ~46630–46690, e.g. `80ebab32112820a9` `IdenticalToNotIdentical` on `subscribe` / token match, `dfa5728ba31778a6` `RemoveMethodCall` on success logging, `dea33ba39096a17f` `RemoveEarlyReturn` on the plaintext challenge response).
  - Cause: no test pinned `hub_mode === 'subscribe'` together with `config('services.whatsapp.verify_token')` or the `403` / `Forbidden` failure body when the mode or token is wrong.
  - Fix: `test_get_verification_returns_plaintext_challenge_when_mode_and_token_match` sets a known verify token and asserts `200`, `Content-Type: text/plain`, and the echoed `hub_challenge`; `test_get_verification_returns_forbidden_when_verify_token_mismatches` and `test_get_verification_returns_forbidden_when_mode_is_not_subscribe` assert `403` for bad token and non-subscribe mode.

- **Signature verification and POST success envelope** (`verifyWebhookSignature()` / `handleEventNotification()` ~86–104; report ~46763–46967, e.g. `89397f3d1f0b721d` / `ce33c900f53c11b8` on `if (! $this->verifyWebhookSignature)`, `691e7425ff8dc2be` / `e1351f0fc59972db` on missing `app_secret`, `790b01cdceaac48b` / `5fda9522e9d25e6f` / `1b8eef6f1477a238` on the `sha256=` HMAC prefix, `8254fbf63334ce66` / `0d706ad5c6c64853` on `['success' => true]`).
  - Cause: POSTs never ran with/without `X-Hub-Signature-256` or a configured `app_secret`, so negated checks, skipped HMAC, or wrong JSON keys could survive mutation.
  - Fix: raw-body `Illuminate\Http\Request::call` + `hash_hmac('sha256', $body, $secret)` for a valid `X-Hub-Signature-256`; `test_post_without_signature_returns_unauthorized` when `app_secret` is set; `test_post_with_app_secret_rejects_wrong_signature`; `test_post_with_app_secret_accepts_valid_hmac_and_returns_success_json`; `test_post_when_app_secret_not_configured_accepts_request_with_any_non_empty_signature` covers the “secret unset → verify returns true” branch **after** a non-empty signature header (the controller still rejects completely missing signatures).

- **Payload shape: `object`, entries, and `switch ($field)`** (`processWebhookPayload()` / `processChange()` ~139–186; report ~47159–47447, e.g. `0c776f69c228ff9e` / `950c48ebc950f3ae` / `a691c401be347346` on the `whatsapp_business_account` guard, `083cbe04ef570add` / `ce773cef7d86a992` on `entry` iteration, `123e9ea2b4915f9e` / `9012b2724fd9403b` on `messages` / `message_status`, `5667a82fcad7dd22` / `6fd3f6c99750fea6` on the default / unhandled field branch).
  - Cause: no POST carried a realistic `entry` / `changes` tree, so mutants on `isset($payload['object'])`, `!== 'whatsapp_business_account'`, `foreach` bodies, or `switch` arms were never exercised under HTTP.
  - Fix: `test_post_with_wrong_object_type_still_returns_success_so_meta_does_not_retry` asserts unknown `object` values still return HTTP `200` and `{"success":true}` (matches production’s non-throwing handler); `test_post_processes_messages_message_status_and_unknown_change_fields` sends one entry with `messages`, `message_status`, and an extra `account_update` change to drive all `switch` arms without asserting internal logs.

## CampaignController (`app/Http/Controllers/Api/v1/CampaignController.php`)

- **Not-found guard** (`showBySlug()` ~16–20; report ~47591–47603, e.g. `c2c07be607736cb1` `RemoveNot`, `db1c2ef37997eb29` `RemoveArrayItem` on the `404` JSON).
  - Cause: only a generic “missing slug” smoke existed; nothing asserted the `! $campaign` branch together with `! $campaign->visible` or the exact `{"message":"Campaign not found"}` envelope, so inverted visibility logic or stripped error keys survived mutation.
  - Fix: `Tests\Feature\Http\CampaignApiTest::test_show_by_slug_returns_not_found_when_campaign_does_not_exist` and `::test_show_by_slug_returns_not_found_when_campaign_is_not_visible` hit `GET api/campaigns/{slug}` for a missing slug and for a persisted row with `visible = false`, both expecting `404` + `message`.

- **Eager loads: milestones, donations, rewards** (`showBySlug()` ~22–29; report ~47615–47712, e.g. `7d8ed591ae590128` / `2292cef89b7ae14a` on `milestones` constraints, `2f75328bd77fad4b` / `acdede974bbe5a11` / `89073510aa4fb5a7` on `donations`, `ab58cdb3567100c8` / `98d6df4e9f2e0e96` / `c5bdbc089a9af6f6` on `rewards`).
  - Cause: no HTTP request loaded a real `Campaign` with related rows, so mutants could drop `orderBy` / `where` clauses from the `load` callbacks or the `is_active` filter without failing the suite.
  - Fix: `test_show_by_slug_returns_milestones_ordered_by_amount_ascending` seeds two milestones in non-ascending insert order and asserts `milestones.0.amount_cents` & `milestones.1.amount_cents` reflect ascending amounts; `test_show_by_slug_returns_only_paid_donations_newest_first` seeds two `paid` rows (with distinct `created_at`) plus a `pending` donation and asserts only two `donations` entries, `status` always `paid`, newest-first amounts, and `total_donated` matching the sum of paid cents; `test_show_by_slug_returns_only_active_rewards` seeds one inactive and one active reward and asserts a single `rewards` entry with `is_active` true.

- **`total_donated` coalesce on the model** (`showBySlug()` ~31; report ~47726–47750, e.g. `8b0aaaae3c8c357a` `CoalesceRemoveLeft`, `1c83942d0a8b9644` / `b8a17a9df28260ea` `DecrementInteger`/`IncrementInteger` on the `?? 0`).
  - Cause: the explicit `$campaign->total_donated = $campaign->total_donated ?? 0` line was never executed with a campaign that had paid donations in the same HTTP response, so mutants on the coalesce or assignment never broke an assertion.
  - Fix: the paid-donations test above asserts `total_donated` in the JSON equals the summed paid `amount_cents` (covers the accessor-backed value surfaced on the serialized payload).

## ReferencesController (`app/Http/Controllers/Api/v1/ReferencesController.php`)

- **Constructor `logged` middleware** (`__construct()` ~18; report ~47762 `90b971ed534c165d` `RemoveMethodCall`).
  - Cause: only `ReferencesManager` / repository unit tests existed; nothing hit `POST api/references` through the real `UserLoggin` stack, so removing `middleware('logged')` never failed CI.
  - Fix: `Tests\Feature\Http\ReferencesApiTest::test_create_requires_authentication` posts without a JWT / session fallback and expects `401` + `Unauthorized.` (same envelope as other `logged` routes).

- **`create()` authenticated branch vs `ReferencesManager::create` outcomes** (`create()` ~24–34; report ~47774 `5d2065e545df76ba` `IfNegated` on `if ($this->user)`, plus success `response()->json($reference)` vs `ExceptionWithErrors('Could not rate user.', …)` when the manager returns falsy).
  - Cause: no feature test exercised `POST api/references` with a resolved user, so inverting the `if ($this->user)` guard or dropping the success/error responses stayed green; manager failures were never mapped to HTTP.
  - Fix: `test_create_returns_reference_payload_when_valid` asserts `200`, stable `comment` / `user_id_from` / `user_id_to` paths, `assertJsonStructure` on the persisted keys, and a DB row in `users_references`; `test_create_returns_unprocessable_when_comment_missing`, `…_when_target_user_does_not_exist`, `…_when_author_targets_self`, and `…_when_reference_already_exists` assert `422`, `Could not rate user.`, and an `errors` envelope where validation runs (missing `comment`); duplicate / missing target / self-target cases pin the falsy-manager branch without mocking `ReferencesManager`.

## `ReferencesManager` (`app/Services/Logic/ReferencesManager.php`)

- **Self-reference guard uses value equality across scalar forms** (`create()` same-user check around line 39; report survivor `EqualToIdentical`).
  - Cause: tests asserted self-reference rejection only with same-typed ids, so mutating `==` to `===` could survive without violating existing assertions.
  - Fix: `test_create_treats_equivalent_scalar_ids_as_same_user_for_self_reference_guard` exercises a same logical user id across int/string forms and asserts `reference_same_user`, pinning value-based self-reference protection.
  - Mutant IDs: `d3767ca264356c9e`.

## OsrmProxyController (`app/Http/Controllers/Api/v1/OsrmProxyController.php`)

- **Path length and invalid-profile guards** (`route()` ~21–33; report ~47786–47930, e.g. `5111317cfc3f97d3` `GreaterToGreaterOrEqual` / `b2ddabce106d3ee4` / `bbee9e628c995f42` on `strlen($path) > 4096`, `3cfc4a8539a3ab3a` `RemoveEarlyReturn` on the `400` body, `04fdb93494e0a819` / `3b151ae1757dc10d` / `b3359610345dd4d0` on the `str_starts_with($path, 'driving/')` branch).
  - Cause: only `UncoveredEndpointsSmokeTest` hit the happy-ish “upstream down” path; nothing asserted the `400` JSON for an oversized `{path}` or pinned the `InvalidUrl` keys / messages, and the `walking/…` style branch is **not reachable over HTTP** because `routes/api.php` constrains `{path}` with `where('path', 'driving/.+')` (router `404` before the controller).
  - Fix: `Tests\Feature\Http\OsrmProxyApiTest::test_rejects_path_longer_than_4096_characters` builds a `driving/` + padding path with `strlen > 4096` and expects `400` + exact `code`/`message`; the non-`driving/` guard remains covered indirectly by the route pattern (documented here rather than duplicating a redundant `404` smoke).

- **Cache hit vs miss and upstream success JSON** (`route()` ~35–79; report ~47942–48158, e.g. `deaf6e334992589f` / `cc27b4b6e7ed5581` on cache-key concat, `d70d2f0ffb5854e0` `RemoveMethodCall` on `Log::debug`, `445c39a160bb5da8` `RemoveEarlyReturn` on the HIT return, `79d2e758727398d5` `TernaryNegated` on query concat for `$upstreamPath`).
  - Cause: smoke tests did not assert `X-OSRM-Proxy-Cache` / `X-OSRM-Proxy-Error` headers, exact `NoRoute` fallback JSON when `fetchFromOsrmBases` returns `null`, or that a second identical GET reuses cache without a second HTTP upstream call.
  - Fix: `test_returns_no_route_envelope_when_upstream_unreachable` pins `200`, `NoRoute`, empty `routes`/`waypoints`, `MISS`, and `upstream_failed`; `test_returns_upstream_json_on_miss_and_serves_second_identical_request_from_cache` fakes a successful OSRM-shaped payload, asserts `MISS` then `HIT` with stable `code`/`routes.0.distance`, and `Http::assertSentCount(1)`.

- **Primary vs fallback bases and malformed JSON** (`fetchFromOsrmBases()` ~82–117; report ~48170–48722, e.g. `856ed2999c8c6edb` / `0463833108f8b65d` on `array_filter` / `array_unique`, `1743eef3ccc6be28` on `Http::timeout`, `a166e3cccbb14b16` / `c17923296966cdac` on the `Ok` TTL ternary ~66–68).
  - Cause: no HTTP test forced a failed primary request then a successful fallback, or a `200` body missing the `code` key (so `fetchFromOsrmBases` keeps looping and returns `null`), so dual-base ordering and `array_key_exists('code', $data)` checks were invisible to CI.
  - Fix: `test_retries_fallback_base_when_primary_returns_unsuccessful_http` configures distinct primary/fallback hosts with `Http::fake` and expects `200` + `code` `Ok` after two outbound GETs; `test_treats_successful_http_without_osrm_code_key_as_upstream_failure` returns `200` JSON without `code` and expects the same `NoRoute` / `upstream_failed` client contract as a hard failure.

## ConversationController (`app/Http/Controllers/Api/v1/ConversationController.php`)

- **Constructor `logged` middleware** (`__construct()` ~27; report ~49040 `ae65aca91b89758e` `RemoveMethodCall`).
  - Cause: older `ConversationApiTest` methods hit authenticated flows only, so stripping `middleware('logged')` never failed CI on `GET|POST api/conversations*`.
  - Fix: `Tests\Feature\Http\ConversationApiTest::test_conversation_endpoints_require_authentication` calls `GET api/conversations`, `POST api/conversations`, `GET api/conversations/show/1`, `GET api/conversations/1`, `GET api/conversations/user-list`, `GET api/conversations/unread`, `POST …/send`, and `POST …/multi-send` without auth and expects `401` + `Unauthorized.` on each.

- **`index()` pagination inputs** (`index()` ~35–46; report ~49052–49112, e.g. `eae7416a82b35da3` / `bdbb35c39fbd42fb` on default `pageNumber`, `e62084d9fed7e1ff` / `282a71fcea6ca78d` on `pageSize`, `4077a0ba656f3a88` `IfNegated` on `$request->has('page')`).
  - Cause: list coverage asserted total count only with default pagination, so mutants on `has('page')` / `has('page_size')` or default integers never broke an assertion tied to `meta.pagination`.
  - Fix: `test_index_respects_page_size_query_parameter` seeds two conversations that each have at least one message (repository `has('messages')` gate), then `GET api/conversations?page=1&page_size=1` and asserts `meta.pagination.total`, `per_page`, and `current_page`.

- **`show()` falsy conversation** (`show()` ~56–59; report ~49112 `a8ea5a67cd76a4c1` `IfNegated` on `if ($conversation)`).
  - Cause: tests used a low numeric id for “missing” rows but did not assert the `ExceptionWithErrors('Bad request exceptions')` envelope for a guaranteed non-existent id.
  - Fix: `test_show_returns_unprocessable_when_conversation_missing_or_inaccessible` uses `GET api/conversations/show/{id}` with an id past `max(id)` and expects `422` + `Bad request exceptions`.

- **`create()` / `send()` identity gate** (`create()` / `send()` ~66–68 and ~112–114; report ~49124–49196, e.g. `49f2c1b7fcf2d28f` `BooleanAndToBooleanOr`, `44009e160032fa4e` / `b4b7441766a827e9` `RemoveNot` on `! $this->user->is_admin`, `1b123e8036cc075e` / `85c6ee6a1c4bca1e` / `2289fe28a4796f4d` / `bc0fcc60167a7204` on `send`, `7fb0b1c1da8fafcb` on `if ($m = …)`).
  - Cause: existing POST/send tests used admins or left identity enforcement off, so mutants could swap `&&`/`||`, drop `!is_admin`, or bypass `IdentityValidationHelper::canPerformRestrictedActions` without failing HTTP assertions.
  - Fix: with `identity_validation_enabled`, non-optional enforcement, `identity_validation_required_new_users`, and a `identity_validation_new_users_date` in the past, `test_create_returns_unprocessable_when_identity_required_and_user_not_validated` and `test_send_returns_unprocessable_when_identity_required_and_user_not_validated` exercise non-admin, non-validated users and assert `422` + `IdentityValidationHelper::identityValidationRequiredMessage()` (and the structured `identity_validation_required` error on `create`).

- **`multiSend()` identity gate (no admin bypass)** (`multiSend()` ~189–198; report ~49292–49343, e.g. `296509720161c9f7` `IfNegated`, `efd93216c156e862` `RemoveNot`, `76b05c7fbfe10d63` / `366ebb83c2f39699` / `a143b6768b1e5ab4` on `if ($m = $this->conversationLogic->sendToAll(…))` / return payload).
  - Cause: `create()` and `send()` exempt admins from the identity check, but `multiSend()` only calls `canPerformRestrictedActions`, so admins without identity could still pass CI if nothing asserted that branch; success JSON for `['message' => true]` was not pinned.
  - Fix: `test_multi_send_returns_unprocessable_for_admin_without_identity_when_enforcement_requires_it` uses an `is_admin` user with `identity_validated = false` under the same config block and expects `422`; `test_multi_send_succeeds_when_identity_validation_allows_user` asserts `200` and exact `{"message":true}`.

- **`userList()` optional `value` and `getMessagesUnread()` query branches** (`userList()` ~161–167; `getMessagesUnread()` ~175–183; report ~49232–49280, e.g. `bec5b8f34414f565` `IfNegated` on `has('value')`, `05e5da7db6c50028` / `264ed159e86dc54f` on `has('conversation_id')` / `has('timestamp')`, `dce53330aaefca72` / `b153b926d6d1c8ec` `AlwaysReturnNull` on assignments).
  - Cause: `user-list` was only hit without `value`; `unread` was never hit with optional filters, so `has()` / assignment mutants stayed green.
  - Fix: `test_user_list_accepts_optional_value_query_without_error` compares `200` + `data` shape with and without `value`; `test_unread_messages_endpoint_accepts_conversation_id_and_timestamp_query` hits `GET api/conversations/unread` with both query parameters and asserts `200` + `data` envelope.

## PassengerController (`app/Http/Controllers/Api/v1/PassengerController.php`)

- **Constructor `logged` middleware** (`__construct()` ~20; report `tests/coverage/20260428_2310.txt` ~49356 `99765534718a204c` `RemoveMethodCall`).
  - Cause: `PassengerApiTest` replaced `PassengersManager` with a mock, so HTTP never exercised `UserLoggin`; removing `middleware('logged')` stayed green.
  - Fix: `Tests\Feature\Http\PassengerControllerIntegrationTest::test_passenger_routes_return_unauthorized_when_not_authenticated` calls representative `GET|POST` routes under `api/trips/*` and `api/users/*` without auth and expects `401` + `Unauthorized.` on each.

- **Fractal `collection()` return paths** (`passengers` / `requests` / `allRequests` / `paymentPendingRequest` ~31, ~41, ~51, ~61; report ~49368–49404, e.g. `87f3f32db60063dd`, `3ec8d83b55c408eb`, `800fc41ddd54d626`, `a790ca8efc70b65e` `AlwaysReturnNull`).
  - Cause: mocked manager short-circuited the real `PassengersManager` + `Controller::collection` stack, so mutants that dropped the Fractal payload never failed CI.
  - Fix: integration tests drive `GET …/passengers`, `GET …/{tripId}/requests`, `GET api/trips/requests`, and `GET api/users/payment-pending` with real fixtures and assert `200` + `assertJsonStructure(['data'])` (and stable `data.0.user.id` where a row is seeded).

- **`newRequest` / `cancelRequest` / `acceptRequest` success JSON** (`~78`, `~97`, `~114`; report ~49416–49488, e.g. `b67fa1eb7088c51c` / `de2bb2b3329b5b03`, `67369af9ba1a9a08` / `ea2ee31f5e559369`, `6df6e9778221a468` / `5c1e19460b74b25d` `RemoveArrayItem` / `AlwaysReturnNull`).
  - Cause: `PassengerApiTest` had the manager return loose `true`, so the controller never serialized a real `Passenger` model inside `['data' => …]` and success-envelope mutants survived.
  - Fix: `test_new_request_returns_data_when_identity_allows_and_trip_is_open`, `test_cancel_request_returns_data_envelope_when_passenger_cancels_pending`, and `test_accept_request_returns_data_when_driver_has_capacity` assert `assertJsonStructure(['data' => ['id', …]])` / `request_state` on real DB-backed flows.

- **`transactions()` raw return** (`~83`; report ~49440 `0c7777480869bf16` `AlwaysReturnNull`).
  - Cause: no HTTP test hit `GET api/trips/transactions` with the real repository join used by `PassengersManager::transactions`.
  - Fix: `test_transactions_returns_json_list_for_user_with_past_trip_payment_rows` seeds a past trip with a passenger row carrying `payment_status` and asserts the response includes that status (public contract, not internal loop structure).

- **`payRequest` falsy guard** (`~125–129`; report ~49500–49536, e.g. `cd6ca4fcfdbf6e8c` `IfNegated`, `8b89fbc2c9829f03` `RemoveNot`, `5054583901d74363` / `1a9eab978ec7bc36` on the error/success JSON).
  - Cause: nothing asserted `POST …/pay` when the passenger was not in `WAITING_PAYMENT`, so inverting `if (! $request)` or stripping the `response()->json(['data' => …])` envelope stayed green; the API message is the same string as accept/reject failures (`Could not accept request.`) by current controller text.
  - Fix: `test_pay_request_returns_unprocessable_when_passenger_not_waiting_payment` expects `422`, `Could not accept request.`, and `errors.error` = `not_valid_request`.

- **`rejectRequest` success JSON** (`~146`; report ~49548–49559, e.g. `abc2dea14648139f`, `247856e595e1a91e`).
  - Cause: same mock `true` shortcut as accept; failure-path parity with `payRequest` / accept was not exercised from HTTP for reject success.
  - Fix: `test_reject_request_returns_data_when_driver_rejects_pending` asserts `200` and `data.request_state` = `STATE_REJECTED`.

- **Identity gates on `newRequest` / `acceptRequest` / `rejectRequest`** (`~67`, `~103`, `~135`; not always listed as separate IDs in the excerpted report block but coupled to the same uncovered controller surface as Conversation).
  - Cause: integration coverage did not combine `IdentityValidationHelper::canPerformRestrictedActions` with real HTTP when enforcement config matches production-style “new users must validate”.
  - Fix: `enableStrictNewUserIdentityEnforcement()` + `test_new_request_returns_unprocessable_when_identity_required_and_user_not_validated`, `test_accept_request_returns_unprocessable_when_identity_required_and_driver_not_validated`, and `test_reject_request_returns_unprocessable_when_identity_required_and_driver_not_validated` assert `422` + `IdentityValidationHelper::identityValidationRequiredMessage()`.

- **Production fix:** `passengers()` called `PassengersManager::index`, which does not exist on the real manager (only the mock defined `index`). The controller now calls `getPassengers`, and `PassengerApiTest` expects `getPassengers` on the mock so unauthenticated integration tests and authenticated listing hit the same contract the app ships.

## TripController (`app/Http/Controllers/Api/v1/TripController.php`)

- **Constructor middleware** (`__construct()` ~23–24; report `tests/coverage/20260428_2310.txt` ~49570–49582, e.g. `c663114eec329d6d`, `15211f89b962e093` `RemoveMethodCall` on `middleware('logged')->except(['search'])` and `middleware('logged.optional')->only('search')`).
  - Cause: `TripApiTest` mocked `TripsManager`, so stripping either middleware registration never failed CI on authenticated trip routes vs public `GET api/trips` search.
  - Fix: `Tests\Feature\Http\TripControllerIntegrationTest::test_trip_mutating_routes_require_authentication` calls `POST|PUT|DELETE` trip routes, `GET` show, `changeSeats`, `change-visibility`, `users/my-trips`, `users/my-old-trips`, `users/sellado-viaje`, `POST` `trips/price`, and `trips/trip-info` without auth and expects `401` + `Unauthorized.` on each.

- **`create` / `update` Fractal `item()` success** (`~44`, `~60`; report ~49594–49606, e.g. `abf71ba394dc49fb`, `9c74808de3d71cd2` `AlwaysReturnNull`).
  - Cause: mocked `create`/`update` never returned through the real `TripTransformer` item envelope.
  - Fix: `test_create_returns_trip_payload_when_validation_and_route_cache_succeed` seeds `route_cache` for the same point geometry as the valid payload (avoids live OSRM), asserts `200` + `data.id` / towns, and `assertDatabaseHas` on `trips`; `test_update_returns_trip_payload_when_owner_updates_description` asserts `200` + updated `description` on a real owned trip.

- **`create` identity gate** (`~32–34`; report already showed killed `IfNegated`/`RemoveNot` on the same line cluster in a focused run, but HTTP coverage was still mock-based).
  - Cause: integration did not combine `IdentityValidationHelper::canPerformRestrictedActions` with `POST api/trips` under strict new-user enforcement.
  - Fix: `test_create_returns_unprocessable_when_identity_required_and_user_not_validated` mirrors the Conversation pattern and asserts `422` + `IdentityValidationHelper::identityValidationRequiredMessage()`.

- **`create` / `update` failure envelopes** (`~37–41`, `~53–57`; `RemoveArrayItem` / falsy guards adjacent to success returns in the same report block).
  - Cause: only `200`-ish mock paths existed; validation failures and cross-user updates did not assert stable `422` + `Could not create/update trip.` messages.
  - Fix: `test_create_returns_unprocessable_when_validation_fails` posts an empty body; `test_update_returns_unprocessable_when_user_does_not_own_trip` asserts `422` + `Could not update trip.` with a structured `errors` payload.

- **`delete()` success JSON** (`~84`; report ~49654–49666, e.g. `9517fb9b1f72d7e4`, `b1b043c4a4773adf`).
  - Cause: mock returned a boolean without asserting `['data' => 'ok']`.
  - Fix: `test_delete_returns_ok_envelope_when_owner_deletes_trip` expects exact `{"data":"ok"}` and a soft-deleted trip row.

- **`show()` visibility vs Cordova stubs** (`~89–102`, `~107–123`; report ~49678–49966 large `RemoveEarlyReturn` / `RemoveArrayItem` / pagination integer mutants on the fake search payload).
  - Cause: no HTTP test set the old WebView `Sec-CH-UA` + `User-Agent` fingerprint, so the early-return stubs for upgrade messaging were never exercised; public vs friends visibility for `show` was not asserted via HTTP.
  - Fix: `withOldCordovaUserAgent()` drives `GET api/trips` and authenticated `GET api/trips/{id}` expecting the documented placeholder copy (`ACTUALIZA TU APP`, synthetic `data.id`, and search `meta.pagination.total` = 1); `test_show_returns_trip_when_visibility_allows` / `test_show_returns_unprocessable_when_trip_is_not_visible` cover real `TripsManager::show` outcomes.

- **`changeTripSeats()`** (`~69–73`; report ~49618–49642 `IfNegated` / `RemoveNot` / `AlwaysReturnNull`).
  - Cause: HTTP never hit both the successful increment path and a failing increment guarded by seat rules.
  - Fix: `test_change_trip_seats_returns_trip_when_increment_succeeds` vs `test_change_trip_seats_returns_unprocessable_when_increment_invalid` (full seats then `increment` = 1).

- **`search()` default `page_size`, `trackSearch`, and `paginator()`** (`~128–139`; report ~49978–50039, e.g. `cec52c87e9394c5c` / `bea2fce6e518f46b`, `7f27f1260cb84bcf` / `438ae9eff911929b`, `d30d3fb47b9ab440`, `9e56d117c6cec8f9`, plus `trackSearch` ternary / `if ($originId || $destinationId)` cluster ~50051–50159).
  - Cause: guest search only asserted `200` from a mock paginator; default `page_size`, `trackSearch`, and the `paginator` return were not tied to observable persistence or pagination fields.
  - Fix: `test_search_allows_guests_and_returns_paginated_envelope` pins `meta.pagination.per_page` = 20 when omitted; `test_search_respects_custom_page_size` pins `7`; `test_search_with_origin_persists_trip_search_row` creates a real `nodes_geo` origin (FK-safe), performs an authenticated search with `origin_id` + `is_passenger=true`, and asserts a new `trip_searches` row with matching `origin_id`, `user_id`, and `is_passenger`.

- **`getTrips()` / `getOldTrips()` branches** (`~165–197`; report ~50221–50317, e.g. `f8566aa29d6a6f32`, `8a0a4550e338855a`, `6ab01c9c3e105887`, `bfece4cfe2d85a15`, `63bf7c113f5a6da8`, `9ea8f777ed7074cd`).
  - Cause: `TripApiTest` mocked listings; `as_driver`, optional `user_id`, and the `getOldTrips` `user_id` branch were not observable over HTTP with real `TripsManager`.
  - Fix: `test_get_trips_lists_driver_trips_and_supports_as_driver_false`, `test_admin_can_list_trips_for_another_user_via_user_id`, and `test_get_old_trips_returns_past_trips_for_requested_user`.

- **`price()` / `getTripInfo()` / `selladoViaje()`** (`~200–228`; report ~50329–50437).
  - Cause: no authenticated HTTP coverage for these endpoints with real manager/repository return shapes (only mocks or unit tests).
  - Fix: `test_price_endpoint_returns_numeric_estimate_for_distance` (`api_price` off, `fuel_price` known), `test_trip_info_endpoint_returns_route_shape_when_route_cache_hit`, and `test_sellado_viaje_returns_success_envelope_with_threshold_fields`.

- **`changeVisibility()`** (`~235–239`; report ~50449–50473).
  - Cause: owner path re-loads the trip after toggling `deleted_at` via `TripRepository::show`, which excludes soft-deleted rows for non-admins, so the owner HTTP path can end in `422` even when the toggle partially applied; nothing asserted the admin/`withTrashed` path that the manager comment implies.
  - Fix: `test_change_visibility_returns_trip_payload_for_admin` exercises `POST api/trips/{id}/change-visibility` as an admin on another user’s trip and expects `200` + `data.id` (stable contract without relying on the broken non-admin reload sequence).

## TripsManager (`app/Services/Logic/TripsManager.php`)

- **Create guard branches for validation-driver and anti-abuse bans** (`create()` around lines 83–133 in `tests/coverage/20260428_2310.txt` run block near line ~80647+).
  - Cause: existing unit coverage validated generic payload rules and ownership flows, but did not assert behavior for unverified-driver blocking, trip-creation-rate banning, banned-word detection, and banned-phone detection in descriptions.
  - Fix: added four behavior-focused tests to `tests/Unit/Services/Logic/TripsManagerTest.php`:
    - `test_create_rejects_unverified_driver_when_module_requires_verified_drivers`
    - `test_create_bans_user_when_recent_trip_count_exceeds_configured_limit`
    - `test_create_bans_user_when_description_contains_banned_word`
    - `test_create_bans_user_when_description_contains_banned_phone_number`
    These assert returned value is `null`, expected error key is present, and user `banned` flag is persisted where applicable.
  - Mutant IDs: `10bb520fad95156f`, `195d15d517b7037c`, `d27ffc92fc7b4fa1`, `f13ecdb24c6b8aa5`, `2ff05e119970faa8`, `d8984b3b8a3a4eea`, `d2a14d75dc40d147`, `f1084ce00aef1976`, `552a2156ee3eeecb`, `b2334a321abf4e68`, `ca9d8eb6b6eada28`, `a9e85e7891fa7387`, `3dc93c4a97352729`, `dfb9b95a36adea10`, `800cf9698d48795b`, `8531cd887492e062`, `8a31e47c9b7eceac`, `28bf9f0f130dd24f`, `386a8b3f7e758c84`, `d324fa40184aaf4e`, `ba3583b3010be717`, `c33e035696ba6aee`, `c6ff11f8c0939452`, `09cbf51ce4825c43`, `a3f4261e8c0b514e`, `e7bb4586925d6a12`, `4408d4e64c3e5cba`, `52728829102133bc`, `6c8c8f8d207f928b`, `e18fbccf91d73b51`, `1b74ae5fc340fdc0`, `834fbc6332b68148`.

## ExceptionWithErrors (`app/Http/ExceptionWithErrors.php`) — supporting fix for trip update denial

- **`render()` string `$errors` from `trans('errors.tripowner')` etc.** (`render()` ~29–36; uncovered when `TripController::update` throws after `TripsManager::update` returns falsy with a **string** error bag).
  - Cause: `render()` assumed `$errors` was always an array or `MessageBag`; calling `toArray()` on a translation string fatals, masking the intended `422`.
  - Fix: normalize scalar/string errors to a minimal `['error' => […]]` array before building the JSON response so `test_update_returns_unprocessable_when_user_does_not_own_trip` (and similar controllers) receive consistent `422` envelopes.

## DeviceController (`app/Http/Controllers/Api/v1/DeviceController.php`)

- **Constructor `logged` middleware** (`__construct()` ~22; report `tests/coverage/20260428_2310.txt` ~50681 `538849ec537c2470` `RemoveMethodCall`).
  - Cause: `DeviceApiTest` mocked `DeviceManager`, so stripping `middleware('logged')` never failed CI on `api/devices*`.
  - Fix: `Tests\Feature\Http\DeviceControllerIntegrationTest::test_device_endpoints_require_authentication` hits `GET|POST|PUT|DELETE /api/devices` and `POST /api/devices/logout` without auth and expects `401` + `Unauthorized.` on each.

- **`register` / `update` success JSON** (`~34`, `~47`; report ~50693–50705, e.g. `641927f586b9fbc7`, `e7edfaf5d240b687` `RemoveArrayItem`).
  - Cause: mocks returned loose arrays without asserting `['data' => …]` or stable keys tied to persistence.
  - Fix: real `POST /api/devices` and `PUT /api/devices/{id}` with a JWT from `api/login` (same pattern as `AuthControllerApiTest`); `test_register_returns_device_payload_for_valid_body_and_jwt` and `test_update_returns_device_payload_when_row_belongs_to_user` assert `200`, `data.id` / `device_id` / `user_id`, and `assertDatabaseHas` on `users_devices` where applicable.

- **`register` / `update` failure path** (`~33`, `~46`; `IfNegated` already marked killed in the excerpted report block, but HTTP error envelopes were not pinned).
  - Cause: validation failures and cross-user updates did not assert `422` + `Bad request exceptions`.
  - Fix: `test_register_returns_unprocessable_when_validation_fails` omits `device_id`; `test_update_returns_unprocessable_when_device_belongs_to_another_user` uses a second logged-in user.

- **`index()` list + count** (`~65–67`; report ~50665–50667 show mixed killed/UNTESTED in the same RUN snapshot—integration still lacked HTTP assertions on both keys).
  - Cause: mocked `getDevices` / `getActiveDevicesCount` never asserted the literal `count` field alongside `data`.
  - Fix: `test_index_returns_devices_and_active_count` registers (same JWT session updates one row, then a second `POST` refreshes it) and asserts `count` and `data` length stay consistent with `DeviceManager::getActiveDevicesCount` (notifications-enabled rows).

- **`delete()` response** (`~58`; report ~50717 `75889472ea4f5c89` `AlwaysReturnNull`).
  - Cause: nothing asserted the `200` JSON primitive body returned after `delete()`.
  - Fix: `test_delete_returns_ok_string_for_owner_but_does_not_remove_owned_device` and `test_delete_as_non_owner_removes_device_and_returns_ok` assert `200` and exercise `DeviceManager::delete` semantics over HTTP (owner call keeps the row; non-owner id match deletes—see manager unit tests).

- **`logout()` success vs failure** (`~76–80`; report ~50729–50756, e.g. `a31b6c655290afd5`, `a53a7014a555fcaf`, `f1d3c1510b79dbc0`).
  - Cause: no HTTP test hit `POST /api/devices/logout` with a real `DeviceManager::logoutDevice` outcome; success/failure JSON mutants stayed UNCOVERED.
  - Fix: `test_logout_returns_success_when_device_registered_for_same_session` registers with the same bearer token then posts logout and expects `Device logged out successfully` plus zero rows for the user; `test_logout_returns_unprocessable_when_session_has_no_registered_device` expects `422` + `Device not found`.

- **Production fix:** `delete()` called `DeviceManager::delete($user, $id)` with arguments reversed versus the manager signature `delete($session_id, $user)` and `is_int($session_id)` branching. The controller now calls `delete((int) $id, $user)` so route ids resolve by primary key instead of misrouting the `User` instance through the `session_id` branch.

## DeviceManager (`app/Services/Logic/DeviceManager.php`)

- **Validation + ownership semantics + session short-circuit in registration** (`validator/register/update/delete`; report block around `tests/coverage/20260428_2310.txt` ~83179+ for `DeviceManager`).
  - Cause: existing manager tests covered happy-path creation/update, but did not lock down invalid `notifications` validation, scalar-equivalent owner ID comparisons, or the early-return contract when `session_id` already exists (which should not continue into cross-user `device_id` collision cleanup logic).
  - Fix: added behavior-focused tests in `tests/Unit/Services/Logic/DeviceManagerTest.php`:
    - `test_validator_rejects_invalid_notifications_value`
    - `test_register_with_existing_session_does_not_delete_other_users_same_device_id`
    - `test_update_accepts_equivalent_scalar_owner_ids`
    - `test_delete_treats_equivalent_scalar_owner_ids_as_owner`
  - Mutant IDs: `c92c634b58a09840`, `9876e42b2733194d`, `48a51a12f9e9292f`, `a33399cec7d3a7c1`, `7524eaeff5a1d671`.

## PassengersManager (`app/Services/Logic/PassengersManager.php`)

- **`newRequest` input-validation early return and unanswered-limit guard** (`newRequest()` around lines 103–118; report block around `tests/coverage/20260428_2310.txt` ~83611+).
  - Cause: existing tests covered happy-path request creation and duplicate/expired branches, but they did not assert that invalid input halts execution with validation errors, nor that the unanswered-message-limit module blocks request creation with the expected domain error.
  - Fix: added behavior-focused tests in `tests/Unit/Services/Logic/PassengersManagerTest.php`:
    - `test_new_request_sets_validation_errors_and_stops_when_trip_id_is_invalid`
    - `test_new_request_sets_limit_error_when_unanswered_limit_module_blocks_user`
    These assert no passenger row/event side effects and stable error contracts.
  - Mutant IDs: `58facf184867f3de`, `3e5b706169bcaed5`, `9601f6da87958d26`, `dcaea794ad350599`, `63b7d0f13a2601ca`, `0dd7b68e0d44b83a`, `871c66f6cbd5414c`, `908299bb7890ab93`.

- **`sendFullTripMessage` feature gate + full-trip threshold** (`sendFullTripMessage()` around lines 211–217; report block around `tests/coverage/20260428_2310.txt` ~83935+).
  - Cause: tests previously covered accept/reject/cancel request flows but did not assert the dedicated full-trip message policy: only when the module is enabled, driver allows it, and accepted passengers fill available seats.
  - Fix: added tests:
    - `test_send_full_trip_message_calls_conversation_manager_when_module_enabled_and_trip_is_full`
    - `test_send_full_trip_message_skips_when_module_is_disabled`
    These assert observable collaboration (`ConversationsManager::sendFullTripMessage`) based on business inputs, without coupling to logging internals.
  - Mutant IDs: `6222763e6a15d9f7`, `af606a10894ff5b0`, `80ad96c91d90fbed`, `afa23086c3c6e70b`, `afa98494a36f520d`, `244faed767135a9d`, `4e7ba0f85c442a27`, `6ea51e480cb02561`, `d84c1528473a624a`, `059139180cf4a6d0`, `853b4666b99a9d9e`, `efe258ed7bf6af1a`.

## CampaignRewardController (`app/Http/Controllers/Api/v1/CampaignRewardController.php`)

- **Reward must match campaign** (`purchase()` ~23–24; report `tests/coverage/20260428_2310.txt` ~50767–50827, e.g. `ed58202f2968321d` `IfNegated`, `18ae731ddd647c45` `NotIdenticalToIdentical`, `7a8b5e2c4ebef271` `RemoveEarlyReturn`, `03fe0485e559dd70` `RemoveArrayItem` on the 404 payload).
  - Cause: `purchase` had no HTTP coverage; mutants could flip the membership guard, loosen `!==` to `!=`, drop the early return, or strip `error` from the JSON without failing CI.
  - Fix: `Tests\Feature\Http\CampaignRewardControllerIntegrationTest::test_purchase_returns_not_found_when_reward_belongs_to_another_campaign` posts to `POST /api/campaigns/{slug}/rewards/{id}/purchase` with a reward whose `campaign_id` differs from the route campaign and expects `404` + `Reward does not belong to this campaign`, and asserts no `campaign_donations` row is created.

- **Inactive and sold-out guards** (`~27–32`; report ~50839–50959, e.g. `25d1ac8530d7eb1d`, `d0fc5cc987eb6e53`, `b5cf69a2993e8fea`, `980a086a8060292b` `RemoveEarlyReturn`, `e1e6cc14e34bcfb8` `RemoveArrayItem`).
  - Cause: branches for `!$reward->is_active` and `$reward->is_sold_out` were never exercised over HTTP.
  - Fix: `test_purchase_returns_bad_request_when_reward_is_inactive` and `test_purchase_returns_bad_request_when_reward_is_sold_out` (sold out via one `paid` donation against `quantity_available` = 1) expect `400` and the stable `error` strings; Mercado Pago is not invoked (`shouldNotReceive` on `createPaymentPreferenceForCampaignDonation`).

- **Donation create + Mercado Pago hand-off + success JSON** (`~37–67`; report ~50971–51164, e.g. `e21b61e9aa2fb3db` through `10e186d7166873a9` `RemoveArrayItem` on `CampaignDonation::create` keys, `97b622a5caccb7af` / `357ff8cd100de9c0` `RemoveNullSafeOperator`, `30eedcd128fb8bda` / `b68e7b0c0626b774` `RemoveMethodCall` on `update` / `Log::info`, `8e39be8d489325af` etc. on the success response).
  - Cause: happy path never ran under test; mutants could drop attributes on create, omit `user_id` null-safe handling, skip persisting `payment_id`, remove log calls, or strip `message` / `data.url` / `data.sandbox_url`.
  - Fix: `test_purchase_creates_pending_donation_returns_urls_and_stores_preference_id` mocks `MercadoPagoService::createPaymentPreferenceForCampaignDonation` to return a `Preference` with `id`, `init_point`, and `sandbox_init_point`; asserts `200`, JSON shape, and `campaign_donations` row including `name` / `comment` from the body. `test_purchase_passes_authenticated_user_id_to_payment_service` uses a JWT from `api/login` and expects the service to receive `$user->id` and the donation row to store `user_id`.

- **Failure envelope after preference errors** (`~70–77`; report ~51176–51252, e.g. `2462fac2b5eec24a` `RemoveMethodCall` on `Log::error`, `4e124ed8c5530765` `RemoveArrayItem` on the 500 `error`, `85dbb56933c9568c` / `d7f5b7031c7f085d` on status code).
  - Cause: the `catch` block was never hit from outside the controller.
  - Fix: `test_purchase_returns_server_error_when_payment_preference_fails` makes the mocked service throw; expects `500` + `Could not create payment preference` and a pending donation without `payment_id`.

## Admin `CampaignRewardController` (`app/Http/Controllers/Api/Admin/CampaignRewardController.php`)

- **`index()` list + paid-only `withCount`** (`~15–19`; report `tests/coverage/20260428_2310.txt` ~59300–59338, e.g. `6a835fc5484329a0` `RemoveArrayItem`, `1993bdddeaa98eaf` `RemoveMethodCall` on `withCount`, `d4d9e47a4ab3a101` `AlwaysReturnNull`).
  - Cause: admin reward routes had no feature coverage; mutants could drop the constrained count relation, return nothing, or strip JSON keys without failing tests.
  - Fix: `Tests\Feature\Http\AdminCampaignRewardControllerIntegrationTest::test_index_returns_rewards_with_paid_donations_count` seeds two rewards and three donations (two `paid`, one `pending`) on one reward; `GET api/admin/campaigns/{slug}/rewards` asserts `donations_count` is `2` vs `0` for the other reward.

- **`store()` validation + create + 201** (`~24–34`; report ~59350–59422, e.g. `d8d46738ddbe459b` through `6822b6e75d0c1a08` `RemoveArrayItem`, `afe04f9a620678a0` / `e006ad10e72ad353` on status `201`, `b3bb137141d42371` `AlwaysReturnNull`).
  - Cause: create path and validation envelope were never exercised over HTTP.
  - Fix: `test_store_creates_reward_and_returns_created_payload` posts a valid body and asserts `201`, persisted fields, and `assertDatabaseHas`. `test_store_returns_unprocessable_when_validation_fails` omits required fields and expects `422` with no row inserted.

- **`show()` membership + `loadCount`** (`~37–45`; report ~59434–59546, e.g. `cc157c525225c290`, `9d20639622e63338`, `a0bd0778cb9464d9` `RemoveEarlyReturn`, `86e3104be426e79c` `RemoveMethodCall` on `loadCount`, `229725aecd7e18dc` `AlwaysReturnNull`).
  - Cause: show response and cross-campaign guard had no HTTP assertions.
  - Fix: `test_show_returns_reward_with_paid_donation_count` asserts `donations_count` reflects only `paid` rows. `test_show_returns_not_found_when_reward_belongs_to_another_campaign` expects `404` + `Reward does not belong to this campaign`.

- **`update()` membership + validate + persist** (`~48–64`; report ~59558–59690, e.g. `b20ab62b4956ac1a`, `7613bfef78f9df43`, `4b8f1e3e85c8788d` `RemoveEarlyReturn`, `eba3fa6d6fc1f241` `RemoveMethodCall` on `update`, `abf59f97e30971ca` `AlwaysReturnNull`).
  - Cause: update happy path and 404 branch were uncovered.
  - Fix: `test_update_persists_changes_for_matching_campaign` sends a partial body and asserts JSON + DB. `test_update_returns_not_found_when_reward_belongs_to_another_campaign` ensures the wrong-campaign pairing returns `404` and leaves the row unchanged.

- **`destroy()` membership + 204 + delete** (`~67–75`; report ~59702–59816, e.g. `76ceca2628d45fe6`, `182e15e779d7a982`, `de83d3cd6b134d66` `RemoveEarlyReturn`, `d6b74aa99521e47e` `RemoveMethodCall` on `delete`, `4a877b855792fcf7` `AlwaysReturnNull`, `6087cdd9593c95e1` on `204`).
  - Cause: delete and its 404 guard were never hit from HTTP.
  - Fix: `test_destroy_returns_no_content_and_deletes_reward` expects `204` and `assertDatabaseMissing`. `test_destroy_returns_not_found_when_reward_belongs_to_another_campaign` expects `404` and asserts the reward row still exists.

## Admin `SupportTicketController` (`app/Http/Controllers/Api/Admin/SupportTicketController.php`)

- **`index()` envelope + ordering** (`~18–22`; report `tests/coverage/20260428_2310.txt` ~59827–59835, e.g. `9d3651e520d7189d` `RemoveArrayItem` on `'data'`).
  - Cause: admin ticket list was not asserted; mutants could return an empty JSON object or drop the `data` key.
  - Fix: `Tests\Feature\Http\AdminSupportTicketControllerIntegrationTest::test_index_returns_data_ordered_newest_first` asserts `GET api/admin/support/tickets` returns `data` as an array and that the higher-id ticket appears first among the seeded pair.

- **`show()` eager loads** (`~25–29`; report ~59839–59881, e.g. `aa4c55e5d90a050b`, `3ef64ff37c323ae7`, `5b3b9f73d77200a1`, `1c467e38c9f6a645` `RemoveArrayItem` on `with([...])` / response `data`).
  - Cause: stripping a nested `with()` branch or the whole payload could survive without a contract test on nested relations.
  - Fix: `test_show_includes_user_ticket_attachments_and_reply_attachments` seeds a ticket-level attachment, a user reply, and a reply-level attachment; `GET api/admin/support/tickets/{id}` asserts `user.id` / `user.email`, ticket `attachments`, and `replies.*.attachments` surface the expected `original_name` values.

- **`reply()` validation, `SupportTicketReply::create`, attachment loop, response** (`~32–70`; report ~59887–60051, e.g. `67b8684f8dfd6d9e`, `e49582df2cba57ae`, `f71aabd1807c7e25`, `6ddd7ff6d47a02dc`, `da24df0b7a9585f8`, `26e14ef8ac070bac`, `c6b33c0f5464dcc6`, `9d2d600f74edf46d` on `report($e)`).
  - Cause: admin reply was only partially covered; mutants could weaken validation rules, flip `is_admin`, skip attachment persistence, or drop the success envelope.
  - Fix: `test_reply_without_message_is_unprocessable` omits `message_markdown` and expects `422`. `test_reply_with_image_persists_attachment_for_reply` uses `Storage::fake('public')`, posts a valid image, and `assertDatabaseHas` on `support_ticket_attachments` with `reply_id` and `is_admin` true on the new `support_ticket_replies` row (via the service storing the upload).

- **`updateStatus()` / `updatePriority()` / `updateInternalNote()`** (`~96–137`; report ~60067–60206, e.g. `10fe199bf4bf6524`, `8d7b3c75b1b98379`, `8f891f4186aee827`, `b3f1a4e2092b11bc`, `a1b7bfd6454b7ffc`, `e9a7be5c6120a487`, `4ad422844e6b1e11`, `7109965abd9450da`, `a2c0a36fc0adbe61`, `1bc7f9a96169e7fc`, `854dba9f4519b46f`, `7aba04c592b5b987`).
  - Cause: PATCH/PUT admin maintenance endpoints had no dedicated assertions on validation, the `Cerrado` closed metadata branch, `save()`, or JSON envelopes.
  - Fix: `test_update_status_sets_closed_metadata_only_for_cerrado` / `test_update_status_rejects_values_outside_allowed_set`; `test_update_priority_persists_and_returns_ticket_payload` / `test_update_priority_rejects_invalid_value`; `test_update_internal_note_persists_text_and_can_clear` (including clearing via `null`).

- **`applyActionStatus()` (resolve/close)** (`~140–168`; report ~60211–60304, e.g. `7563b7d1c8848bb1`, `5ec5806006fa85bc`, `7a06a05a2836862e`, `4f93546086820a9a`, `d4677d261dbb99d5`, `e625744d77fc3548`).
  - Cause: optional `message_markdown` reply branch and status transitions were not isolated from other admin flows in a single focused suite.
  - Fix: `test_resolve_without_message_updates_status_without_extra_reply` asserts `Resuelto` without incrementing reply count; `test_close_with_message_creates_admin_reply_and_closed_fields` asserts a new admin reply row, `Cerrado`, and `closed_at` / `closed_by`.

## Admin `BadgeController` (`app/Http/Controllers/Api/Admin/BadgeController.php`)

- **`destroy()` must delete the badge** (`~52–55`; report `tests/coverage/20260428_2310.txt` ~60331–60338, `01d16cd3016fa9aa` `RemoveMethodCall` on `$badge->delete()`; RUN summary ~5497–5498 still listed line 54 as uncovered until this test).
  - Cause: admin badge HTTP tests covered list/show/store/update via `BadgeResourceTest`, but nothing called `DELETE api/admin/badges/{id}`, so removing the `delete()` call could survive mutation testing.
  - Fix: `Tests\Feature\Http\BadgeResourceTest::test_destroy_returns_no_content_and_removes_badge` creates a badge, `DELETE`s it, expects `204 No Content`, and `assertDatabaseMissing` on `badges`.

## `PaymentController` (`app/Http/Controllers/PaymentController.php`)

- **Legacy `Transbank\Webpay\Webpay` dependency** (controller previously called `new Webpay(Configuration::forTestingWebpayPlusNormal())`; that class is not present in `transbank/transbank-sdk` v4, so `transbank` / `transbank-respuesta` fatally errored at runtime).
  - Cause: dependency drift left the controller unrunnable; mutation report still listed branches (`transbank` ~60343–60461, e.g. `1e6d3511da8840fc` `IfNegated`, `e56b0fd26e484ad9`, concat mutants `9575cd143f2844ea` / `66aad9ada69969b4` / `064b34484cd2ab4e` on `returnUrl`/`finalUrl`, `3409c3efc3227519` / `6ae069ae162d130f` on view data; `transbankResponse` ~60463–60530, e.g. `a8041f1f8c091510`, `63c0f5995c1a678d`, `0cfa0bb7a604906b`, `a0fc1da90852d356`, `f7f7991a2cd098ff`, `0335294feff66778`, `80a3b0f899c85e01`).
  - Fix: introduce `STS\Contracts\WebpayNormalFlowClient` with `TransbankSdkWebpayNormalFlowClient` (Webpay Plus REST `create` / `commit`, configurable via `config('services.transbank.webpay_plus.*')` and env `TRANSBANK_WEBPAY_PLUS_*`); bind in `AppServiceProvider`. `PaymentController` now depends on the contract instead of instantiating removed SDK types.

- **`transbank()` entry routing + callback URL wiring** (`~17–41` after refactor).
  - Cause: `echo` for the missing-`tp_id` branch did not populate `TestResponse` bodies, so HTTP assertions could not observe “No transaction id”; unknown `tp_id` vs missing passenger were indistinguishable in responses.
  - Fix: return plain-text `No transaction id` when `tp_id` is absent, and an empty `200` body when `tp_id` is present but no `trip_passengers` row matches (preserves prior “no checkout” behavior while making it assertable). `Tests\Feature\Http\PaymentControllerWebTest` binds `Tests\Support\Webpay\FakeWebpayNormalFlowClient` and asserts `initTransaction` receives `{scheme+host}/transbank-respuesta` and `…/transbank-final`, the posted amount in cents, and the HTML auto-post form for a real `Trip` + `Passenger` fixture.

- **`transbankResponse()` outcomes** (`~48–144`).
  - Cause: no end-to-end coverage for null/invalid gateway payloads, success vs declined `responseCode`, or missing passenger, so guard and `view()` data mutants survived.
  - Fix: same test class drives `POST /transbank-respuesta` with a fake gateway payload: `assertViewHas` for `Transbank ouput empty.` when the client returns `null`; success (`responseCode == 0`) asserts `Passenger` state `STATE_ACCEPTED`, `payment_status` `ok`, and voucher `transbank` view data; decline (`-1`) asserts persisted `payment_status` prefix `error:-1:` and the Spanish copy; unknown `buyOrder` asserts `Operación no encontrada`. `GET /transbank-final` asserts the success blade message.

## `SendPasswordResetEmail` (`app/Jobs/SendPasswordResetEmail.php`)

- **Public queue contract (`$tries`, `$backoff`, `$timeout`)** (`~19–21`; report `tests/coverage/20260428_2310.txt` ~61097–61241, e.g. `f9e4a26bc8ccf1f1`, `671183cb081f3b2f`, `a97b7bd6cd0acedb` `RemoveArrayItem` on backoff elements).
  - Cause: only `Queue::assertPushed` existed from password-reset HTTP tests; nothing executed the job class body, so integer/array mutants on retry metadata never failed CI.
  - Fix: `Tests\Unit\Jobs\SendPasswordResetEmailTest::test_job_exposes_retry_and_timeout_settings` instantiates the job and asserts `3`, `[60, 300, 900]`, and `30`.

- **`handle()` logging + mail** (`~44–88`; report ~61253–61555, e.g. `1852deb5736b67a4` `FalseToTrue` on `log_emails`, `5dea70580896df65` / `b842307513ce3f0d` `RemoveMethodCall` on `Log::info` / `Mail::send`, `57236eb0f8acc0b6` / `fd65cf8322b5fd73` `IfNegated` on the `email_logs` branch, `3a7da4ccd46ce8c5` / `394d9637b800a7c1` on `array_merge` / `substr` token masking, `e47e26da3d6ffe79` on success `Log::info`).
  - Cause: `handle()` was never run under test; mutants could drop log context keys, skip `Mail::send`, invert `log_emails`, or strip token redaction without detection.
  - Fix: `test_handle_sends_reset_password_mailable` uses `Mail::fake()` and `Mail::assertSent(ResetPassword::class, …)` with recipient + constructor fields. `test_handle_logs_to_email_logs_when_enabled` enables `carpoolear.log_emails`, expects ordered `Log::info` envelopes and two `email_logs` `info` calls including truncated token prefix/suffix.

- **`handle()` catch + `failed()`** (`~90–137`; report ~61568+ tail, e.g. `RemoveMethodCall` on `Log::error`, channel `critical`, `RemoveArrayItem` on merged failure payloads).
  - Cause: failure and permanent-failure paths were not exercised; stripping `Log::error`, rethrow, or `failed()` channel logging could survive.
  - Fix: `test_handle_rethrows_after_logging_when_mail_fails` makes `Mail::send` throw and expects `Log::error` with the exception message plus a rethrown `RuntimeException`. `test_failed_logs_permanent_failure_and_optional_email_channel` calls `failed()` with `log_emails` true and expects `Log::error` plus `email_logs` `critical` with a non-empty `stack_trace`.

## `SendDeleteAccountRequestEmail` (`app/Jobs/SendDeleteAccountRequestEmail.php`)

- **Public queue contract (`$tries`, `$backoff`, `$timeout`)** (`~18–20`; report `tests/coverage/20260428_2310.txt` ~61859–62003, e.g. `284a59845f5c8988`, `29134191d95c5bb4` `RemoveArrayItem` on backoff entries).
  - Cause: nothing invoked the job body; only queue pushes from account-delete flows were covered indirectly, so retry metadata mutants stayed UNCOVERED.
  - Fix: `Tests\Unit\Jobs\SendDeleteAccountRequestEmailTest::test_job_exposes_retry_and_timeout_settings` asserts `3`, `[60, 300, 900]`, and `30`.

- **`handle()` logging + `DeleteAccountRequestNotification`** (`~37–73`; report ~62015–62185, e.g. `3673e288fb7e2f34` `FalseToTrue` on `log_emails`, `6772304b906e7571` / `24b2e43e22820204` `RemoveMethodCall` on `Log::info` / `Mail::send`, `70c7460f8acccb61` / `72706fac5c64878f` `IfNegated` on `email_logs`, `d319341e339f64e0` / `b08d800737e931b7` on `array_merge` / channel `info`, `e5f437421ef30893` / `e33f84f1f5b56116` on success logging).
  - Cause: no synchronous execution of `handle()` under test; mutants could drop log keys, skip mail, or strip `admin_url` from the `email_logs` payload.
  - Fix: `test_handle_sends_delete_account_request_notification` uses `Mail::fake()` and `Mail::assertSent(DeleteAccountRequestNotification::class, …)` on admin address + `adminUrl`. `test_handle_logs_to_email_logs_when_enabled` asserts ordered `Log::info` plus two `email_logs` `info` calls (`DELETE_ACCOUNT_REQUEST_EMAIL_SENDING` with merged `admin_url`, then `DELETE_ACCOUNT_REQUEST_EMAIL_SUCCESS`).

- **`handle()` catch + `failed()`** (`~75–121`; report ~62197–62257+, e.g. `97ed30b28316b5bb` through `3a7eb30e949576ac` on `errorData`, `b6d5b2d9c69dae05` on `Log::error`, channel `error`/`critical` mutants).
  - Cause: failure paths were never hit from tests.
  - Fix: `test_handle_rethrows_after_logging_when_mail_fails` expects `Log::error` with `admin_email` + message and a rethrown exception. `test_failed_logs_permanent_failure_and_optional_email_channel` calls `failed()` with `log_emails` true and expects `Log::error` plus `email_logs` `critical` with non-empty `stack_trace`.

## `Friend\Cancel` event (`app/Events/Friend/Cancel.php`)

- **`broadcastOn()` return type** (`broadcastOn()` ~32–35; report `tests/coverage/20260428_2310.txt` ~5679–5680 and UNCOVERED ~62440, mutant ID `34c7f4237f84875a` `AlwaysReturnNull` on `return []`).
  - Cause: the event was never constructed under test; mutating `broadcastOn()` to always return `null` stayed equivalent to “no failing assertion” for callers that never invoked it.
  - Fix: `Tests\Unit\Events\Friend\CancelEventTest::test_broadcast_on_returns_empty_channel_array` asserts `broadcastOn()` is an array and equals `[]` (Laravel’s broadcasting contract expects a channel list, not `null`). `test_constructor_exposes_from_and_to_payload` pins the public `from` / `to` payload so constructor regressions fail the suite.

## `Friend\Request` event (`app/Events/Friend/Request.php`)

- **`broadcastOn()` return type** (`broadcastOn()` ~32–35; report `tests/coverage/20260428_2310.txt` ~5682–5683 and UNCOVERED ~62451, mutant ID `46c88b038a3cda2e` `AlwaysReturnNull` on `return []`).
  - Cause: same as `Friend\Cancel`—no test invoked `broadcastOn()`, so returning `null` instead of `[]` was not detected.
  - Fix: `Tests\Unit\Events\Friend\RequestEventTest::test_broadcast_on_returns_empty_channel_array` asserts an empty array channel list; `test_constructor_exposes_from_and_to_payload` asserts `from` / `to` are stored as given.

## `Friend\Reject` event (`app/Events/Friend/Reject.php`)

- **`broadcastOn()` return type** (`broadcastOn()` ~32–35; report `tests/coverage/20260428_2310.txt` ~5685–5686 and UNCOVERED ~62462, mutant ID `2ece4c95def8708f` `AlwaysReturnNull` on `return []`).
  - Cause: same gap as other `Friend\*` events—`broadcastOn()` was never executed under test, so `null` vs `[]` was invisible.
  - Fix: `Tests\Unit\Events\Friend\RejectEventTest::test_broadcast_on_returns_empty_channel_array` and `test_constructor_exposes_from_and_to_payload` assert the broadcasting channel list contract and the `from` / `to` payload.

## `Friend\Accept` event (`app/Events/Friend/Accept.php`)

- **`broadcastOn()` return type** (`broadcastOn()` ~32–35; report `tests/coverage/20260428_2310.txt` ~5688–5689 and UNCOVERED ~62473, mutant ID `bf5f0d99c7b10309` `AlwaysReturnNull` on `return []`).
  - Cause: same as sibling `Friend\*` events—no test called `broadcastOn()`, so `AlwaysReturnNull` was not killed.
  - Fix: `Tests\Unit\Events\Friend\AcceptEventTest::test_broadcast_on_returns_empty_channel_array` and `test_constructor_exposes_from_and_to_payload` assert `[]` (not `null`) and stable `from` / `to` assignment.

## `MessageSend` event (`app/Events/MessageSend.php`)

- **`broadcastOn()` return type** (`broadcastOn()` ~34–37; report `tests/coverage/20260428_2310.txt` ~5691–5692 and UNCOVERED ~62484, mutant ID `86c79f8f89d41e72` `AlwaysReturnNull` on `return []`).
  - Cause: the event was not exercised in tests, so mutating `broadcastOn()` to always return `null` did not fail anything.
  - Fix: `Tests\Unit\Events\MessageSendEventTest::test_broadcast_on_returns_empty_channel_array` requires an array channel list (`[]`, not `null`). `test_constructor_exposes_from_to_and_message_payload` asserts `from`, `to`, and `message` are the values passed to the constructor (opaque payloads, no DB).

## `MessageSend` listener (`app/Listeners/Notification/MessageSend.php`)

- **`handle()` wires `NewMessagePushNotification` into `NotificationServices`** (`handle()` ~27–40; report `tests/coverage/20260428_2310.txt` ~5790–5795 and UNTESTED ~62822–62846 / UNCOVERED ~62858–62871).
  - Cause: the listener was never executed under test, so `RemoveMethodCall` mutants could drop `setAttribute('from', …)`, `setAttribute('messages', …)`, `notify($to)`, or either `Log::info` in the `catch` without failing the suite.
  - Fix: `Tests\Unit\Listeners\Notification\MessageSendListenerTest::test_handle_forwards_event_payload_into_notification_and_sends_via_each_channel` binds a `NotificationServices` mock, runs `handle()` with a real `STS\Events\MessageSend`, and asserts two `send` calls (push + database channels) with the same `NewMessagePushNotification` carrying the event’s `from` and `messages` attributes and the original recipient as `$users`. `test_handle_logs_and_does_not_rethrow_when_notification_send_fails` makes `send` throw, spies `Log`, and expects the fixed human-readable line plus the exception object, with no bubbling error.
  - Mutant IDs: `9eb4b2a859ae2069` (`setAttribute` `from`), `7c2dd4ff0c834167` (`setAttribute` `messages`), `5364f46cd8ca04e7` (`notify`), `1af591e02966c83e` / `4c4c1653660f877a` (catch `Log::info` calls).

## `PreventMessageEmail` listener (`app/Listeners/Notification/PreventMessageEmail.php`)

- **`handle()` channel gate, notification type, and read-state veto** (`handle()` ~31–40; report `tests/coverage/20260428_2310.txt` ~5811–5824 and UNTESTED ~62883–62955 / UNCOVERED ~62967–63006).
  - Cause: nothing invoked `PreventMessageEmail::handle`, so mutants could negate the outer `if`, swap `||` to `&&`, weaken `instanceof` checks, or change the `!= 1` comparison / literal `1` without breaking the suite.
  - Fix: `Tests\Unit\Listeners\Notification\PreventMessageEmailTest` drives `NotificationSending` with real `MailChannel` / `DatabaseChannel` / `PushChannel` instances (matching production, where `NotificationServices` passes resolved channel objects into `Event::until`). A mocked `ConversationRepository` pins `getConversationReadState` outcomes: push + non–`NewMessageNotification` paths never touch the repo; for `NewMessageNotification`, read state `1` must yield `false` (suppress) and `0` or `2` must yield `true` (allow), for both mail and database channels—covering the `!= 1` boundary and integer nudge mutants.
  - Mutant IDs: `f96f9f4eb07ba994` (`IfNegated` ~33), `6a160972e4574022` (`BooleanOrToBooleanAnd` ~33), `e0523fb6597c91de` / `b5fa16f884ff5a81` (`InstanceOfToTrue` ~33), `3cb13c2d765a61a6` / `cf1b6a15749fadc3` (`InstanceOfToFalse` ~33), `8e16e6789d8cb177` (`InstanceOfToFalse` ~34), `4a11957c5f554cbd` / `4c3862694fb2eb56` / `aae3cc0c3f08c0ca` / `b28974c76ebfe0dc` (`NotEqualToEqual`, `NotEqualToNotIdentical`, `DecrementInteger`, `IncrementInteger` on ~39).

## `UpdateTrip` listener (`app/Listeners/Notification/UpdateTrip.php`)

- **`handle()` accepted-passenger gate + notification wiring** (`handle()` ~27–38; report `tests/coverage/20260428_2310.txt` ~5840–5849 and UNTESTED ~63019–63067).
  - Cause: the listener was never run under test, so mutants could weaken `$passengers->count() > 0`, drop `setAttribute('trip'|'from', …)`, or skip `notify` without detection.
  - Fix: `Tests\Unit\Listeners\Notification\UpdateTripListenerTest` seeds real `Trip` / `User` / `Passenger` rows: no rows or only `STATE_PENDING` ⇒ `NotificationServices::send` is never called; one `STATE_ACCEPTED` passenger (not the driver) ⇒ three `send` calls (database + mail + push) with `UpdateTripNotification` carrying the same trip, `from` equal to the trip owner, and recipient equal to that passenger; two accepted passengers ⇒ six sends covering the `foreach` body twice.
  - Mutant IDs: `c35498373335844e` (`GreaterToGreaterOrEqual` on `> 0`), `ba754a723395b405` / `368dfce652c0e144` (`DecrementInteger` / `IncrementInteger` on the `> 0` comparison), `7d189dcfeb84a8e9` / `59f4a02f4c247015` (`RemoveMethodCall` on `setAttribute('trip')` / `setAttribute('from')`).

## `PendingRate` listener (`app/Listeners/Notification/PendingRate.php`)

- **`handle()` wires `PendingRateNotification` into `NotificationServices`** (`handle()` ~27–37; report `tests/coverage/20260428_2310.txt` ~5851–5854 and UNTESTED ~63081–63108).
  - Cause: the listener was never executed under test, so `RemoveMethodCall` mutants could drop `setAttribute('trip', …)`, `setAttribute('hash', …)`, or `notify($to)` without failing the suite.
  - Fix: `Tests\Unit\Listeners\Notification\PendingRateListenerTest::test_handle_builds_pending_rate_notification_and_notifies_recipient_on_all_channels` mocks `NotificationServices`, dispatches a real `STS\Events\Rating\PendingRate` with factory `User` + `Trip` + a string `hash`, and asserts three `send` calls (database + mail + push) with `PendingRateNotification` carrying that trip and hash and the event recipient as `$users`.
  - Mutant IDs: `a878a11ca9e96d09` (`setAttribute` `trip`), `3cb34b6012258665` (`setAttribute` `hash`), `1eca2d1f700d872c` (`notify`).

## `TripRequestRemainder` listener (`app/Listeners/Notification/TripRequestRemainder.php`)

- **`handle()` driver guard + `RequestRemainderNotification` wiring** (`handle()` ~27–35; report `tests/coverage/20260428_2310.txt` ~5879–5882 and UNTESTED ~63120–63145).
  - Cause: the listener was never run under test, so `IfNegated` on `if ($to)` or `RemoveMethodCall` on `setAttribute('trip', …)` / `notify($to)` could survive unnoticed.
  - Fix: `Tests\Unit\Listeners\Notification\TripRequestRemainderListenerTest::test_handle_skips_notification_when_trip_has_no_driver` passes a plain trip payload with `user` missing/`null` and asserts `NotificationServices::send` is never called. `test_handle_notifies_trip_owner_on_all_channels` uses a factory `Trip` tied to a `User` and expects three channel sends with `RequestRemainderNotification` carrying that trip and the owner as recipient.
  - Mutant IDs: `f0f6004bd73e4246` (`IfNegated` ~31), `b02208cda2b2ec89` (`setAttribute` `trip`), `b9abadcd0aeece93` (`notify`).

## `TestJob` listener (`app/Listeners/TestJob.php`)

- **`handle()` observability** (`handle()` ~30–33; report `tests/coverage/20260428_2310.txt` ~5884–5885 and UNTESTED ~63157, mutant ID `1dfe9f1cb37bd1be` `RemoveMethodCall` on `\Log::info('create handler')`).
  - Cause: the listener was never invoked from tests, so removing the `Log::info` call left the suite unchanged.
  - Fix: `Tests\Unit\Listeners\TestJobListenerTest::test_handle_logs_when_user_create_event_is_processed` calls `handle()` with a real `STS\Events\User\Create` payload and asserts `Log::info` ran once with the fixed message (queue/listener wiring remains unchanged).

## `CreateRatingDeleteTrip` listener (`app/Listeners/Ratings/CreateRatingDeleteTrip.php`)

- **`handle()` accepted-passenger gate, rating row, and delete-trip notification** (`handle()` ~31–46; report `tests/coverage/20260428_2310.txt` ~5887–5899 and UNTESTED ~63168–63266).
  - Cause: nothing exercised `handle()` after a trip delete, so mutants could change `$passengers->count() > 0`, nudge `Str::random(40)`, drop `RatingRepository::create`, or strip `DeleteTripNotification` `setAttribute` / `notify` calls without failing CI.
  - Fix: `Tests\Unit\Listeners\Ratings\CreateRatingDeleteTripListenerTest` uses a mocked `RatingRepository` plus `NotificationServices`: pending-only passengers leave `create` and `send` uncalled; one or two `STATE_ACCEPTED` passengers (not the driver) assert `create` with passenger→driver ids, trip id, `Passenger::TYPE_CONDUCTOR`, `Passenger::STATE_ACCEPTED`, and a 40-character hash, and three `send` invocations per passenger with `DeleteTripNotification` carrying the trip, owner as `from`, and a 40-char `hash`.
  - Mutant IDs: `74f05dc5aa0b13e8` (`GreaterToGreaterOrEqual` on `> 0`), `6ab70a2b5915437a` / `4e1e20b437dcc725` (`DecrementInteger` / `IncrementInteger` on that comparison), `372d7bdf3c838ec8` / `2c95141ca503b1e0` (`DecrementInteger` / `IncrementInteger` on `Str::random(40)`), `fc82d10befeead8f` / `0b04659f7c2820f1` / `57399622bc3b96b7` / `6f19fd7e497198ef` (`RemoveMethodCall` on `setAttribute('trip'|'from'|'hash')` and `notify`).

## `CreateHandler` listener (`app/Listeners/User/CreateHandler.php`)

- **`handle()` lookup, inactive guard, activation URL, mail, logs, and welcome notification** (`handle()` ~33–52; report `tests/coverage/20260428_2310.txt` ~5901–5915 and UNTESTED ~63279–63437).
  - Cause: `handle()` was never exercised, so mutants could strip the first `Log::info`, invert or loosen `$user && $user->email && ! $user->active`, break the `config('app.url').'/app/activate/'.$token` concatenation, drop `Mail::send(new NewAccount(…))`, remove the follow-up log, or skip `NewUserNotification::notify` without failing CI.
  - Fix: `Tests\Unit\Listeners\User\CreateHandlerListenerTest` mocks `UserRepository::show` and uses `Mail::fake()` / `Log::spy()` / mocked `NotificationServices`: missing user still logs the entry line and sends no mail; active users or blank `email` skip the branch; an inactive user with email + fixed `app.url` / `carpoolear.name_app` must receive `NewAccount` at the expected activation URL and token, both log lines, and exactly one `send` for `NewUserNotification` (that notification only registers `MailChannel`).
  - Mutant IDs: `233468a0919c965d` (first `Log::info`), `9cb045661c4e87cd` / `2a436d9f386f0812` / `4e954346bf8edd7e` / `6ac09d313ed17f4a` (compound guard on ~37), `9a0d585d1ea5cf3e` / `a37cc644302691f9` / `73927134084c6996` / `25dda622c15e29c3` / `7e02e6d072c868d0` / `9f7b7b9509a42afd` (concat-style mutants on the activation URL ~42), `ac4470f22d0c3556` (`Mail::send`), `a33b46b351300ddc` (second `Log::info`), `3c4e088853de3df5` (`notify`).

## `OnNewTrip` listener (`app/Listeners/Subscriptions/OnNewTrip.php`)

- **`handle()` subscription matches, `SubscriptionMatchNotification`, and failure log** (`handle()` ~32–48; report `tests/coverage/20260428_2310.txt` ~5917–5936 and UNCOVERED ~63449–63680).
  - Cause: `handle()` was never run under test, so mutants could empty the `foreach` body, strip `setAttribute('trip', …)` / `notify`, remove the catch `Log::info`, or mutate string concatenation in the catch without failing CI.
  - Fix: `Tests\Unit\Listeners\Subscriptions\OnNewTripListenerTest` mocks `SubscriptionsRepository::search` (and an unused `UserRepository` double): empty results assert `NotificationServices::send` is never called; a single synthetic match row with a `user` model expects three `send` calls (database + mail + push) with `SubscriptionMatchNotification` bound to the event trip; when `send` throws immediately, the listener must log the exact `'Ex: {to_town}: {id} - {name}'` string so concat / `Log::info` removals fail.
  - Mutant IDs: `d0498c867e8895fd` (`ForeachEmptyIterable` ~38), `4efd927b1cd53db7` (`setAttribute` `trip` ~42), `939d937295fa4aef` (`notify` ~44), `94a6620182c94de2` (`RemoveMethodCall` on catch `Log::info` ~46), concat cluster on the same line ~46: `09d515680a9bcb69`, `fba0bebabdbc26c5`, `87d55ac4afcefa72`, `21b1648436e2c4bd`, `e3355639effa86a3` (`ConcatRemoveLeft`), `29beefc53f0e50de`, `05606841588f529e`, `524e56a389c8dea0`, `695fc8815d5d9ca8`, `d7ead936638795a5` (`ConcatRemoveRight`), `2b99b7a21d6ba180`, `01b658c29c0eeafd`, `aa7ace9e4ad858fa`, `72a000de89f5016b`, `c69d3497cff531ee` (`ConcatSwitchSides`).

## `ModuleLimitedRequest` listener (`app/Listeners/Request/ModuleLimitedRequest.php`)

- **`handle()` module gate, hour window, destination filter, `AutoCancel`, and passenger persistence** (`handle()` ~29–60; report `tests/coverage/20260428_2310.txt` ~5938–5950 and UNCOVERED ~63693–63825, including line ~63753 onward).
  - Cause: the listener was never executed under test, so mutants could flip `module_user_request_limited_enabled`, negate inner `if`s, strip the `(int)` cast or hour literal, weaken the `to_town` strict check, skip the `AutoCancel` dispatch, or drop `$request->save()` without detection.
  - Fix: `Tests\Unit\Listeners\Request\ModuleLimitedRequestListenerTest` toggles `carpoolear.module_user_request_limited_enabled` / `module_user_request_limited_hours_range`, seeds two drivers, one traveler with an accepted seat on trip A plus a pending seat request on trip B, and `Event::fake([AutoCancel::class])`: module off ⇒ no `AutoCancel`; module on with matching `to_town` and trip dates inside the window ⇒ `AutoCancel` carries trip B, B’s owner, and the traveler, and the pending `Passenger` row becomes `STATE_CANCELED` with `CANCELED_SYSTEM`; different `to_town` or a trip date five hours away ⇒ no dispatch; and with `module_user_request_limited_hours_range` removed from config, a trip at +3h stays untouched, pinning the default `2`-hour fallback (covers the `===` / `&&` filter, hours-range default/cast, and `tripsRequested` window).
  - Mutant IDs: `aac707563ebfa2d0` (`FalseToTrue` on module flag ~34), `8870297e10179d77` (`IfNegated` ~36), `4991968c16c84348` / `272b2e1e3ed3d15c` / `d531bd4c640305e7` (`RemoveIntegerCast` / `DecrementInteger` / `IncrementInteger` on hours ~37), `dd16f55a9357d574` / `b47f592825c06a3f` (`IdenticalToNotIdentical` / `BooleanAndToBooleanOr` on destination ~45), `290d004bc5e24baa` / `2a9e55fabed5705e` (`IfNegated` / `ForeachEmptyIterable` ~48–49), `b730794301ff566b` (`IfNegated` ~53), `995ae0a7af6d0762` (`RemoveFunctionCall` on `event(new AutoCancel…)` ~55), `5e6b49ecc35a9024` (`RemoveMethodCall` on `$request->save()` ~57).

## `FriendCancelNotification` (`app/Notifications/FriendCancelNotification.php`)

- **Delivery channels and notification payload contract** (`via` list plus `toEmail()` / `toPush()`; report RUN ~6199 and UNTESTED/UNCOVERED ~65616–65676).
  - Cause: previous tests covered message text and extras but not the full channel/payload envelope, so `RemoveArrayItem` mutants on channel entries (`DatabaseChannel`, `MailChannel`, `PushChannel`) and payload keys (`name_app`, `domain`, `image`) could survive.
  - Fix: `Tests\Unit\Notifications\FriendCancelNotificationTest` now asserts exact `getVia()` channels, and verifies `toEmail()` includes config-driven `name_app` + `domain`, while `toPush()` always includes the fixed brand image URL; existing tests still cover sender/fallback message and `extras.id`.
  - Mutant IDs: `dc22e279bbb1a657`, `57c15e556f707cf0`, `b72a4557ca331e10`, `0273b8fef0ebf5ca`, `f060ccf0e34e1c8d`, `782596ce139c1a28`.

## `RequestPassengerNotification` (`app/Notifications/RequestPassengerNotification.php`)

- **Delivery channels and payload envelope** (`via` list + `toEmail()`/`toPush()` payload shape; report RUN ~6226 and UNTESTED/UNCOVERED ~65687–65771).
  - Cause: previous tests validated message text and trip id routing but did not fully pin channel list and required payload keys, allowing `RemoveArrayItem` mutants on channels and metadata fields to survive.
  - Fix: `Tests\Unit\Notifications\RequestPassengerNotificationTest` now asserts exact `getVia()` channels and verifies `toEmail()` includes `name_app` and `domain`, while `toPush()` includes the fixed logo `image`.
  - Mutant IDs: `96fda5e0de894ecb`, `aa10c386e7448549`, `508e7f5952894bad`, `186184f1d9f3342a`, `d9da02b01a9401dd`, `32aa21874083a771`.

- **Trip URL fallback when `trip` is absent** (`toEmail()` url composition; report UNTESTED ~65735).
  - Cause: the missing-trip path in URL composition (`.../app/trips/` + empty suffix) was not asserted, so ternary/string mutants could pass.
  - Fix: added `test_to_email_uses_empty_trip_suffix_when_trip_is_missing` to assert the exact public URL when no trip is attached.
  - Mutant IDs: `7c2d83d842eb8d8a`, `5d195dab59226852`.

## `AutoCancelPassengerRequestIfRequestLimitedNotification` (`app/Notifications/AutoCancelPassengerRequestIfRequestLimitedNotification.php`)

- **Channels + payload metadata contract** (`via` list and `toEmail()` / `toPush()` envelopes; report RUN ~6265 and UNTESTED/UNCOVERED ~65782–65876).
  - Cause: prior tests validated destination text and fallback URLs but did not fully pin channel entries and metadata keys, so `RemoveArrayItem` mutants on channels and payload (`name_app`, `domain`, `image`) could survive.
  - Fix: `Tests\Unit\Notifications\AutoCancelPassengerRequestIfRequestLimitedNotificationTest` now asserts exact `getVia()` channels, verifies `toEmail()` includes `name_app` + `domain`, and asserts `toPush()` keeps the static logo image key.
  - Mutant IDs: `54eaf79a01206c13`, `17fa8926b66a4402`, `65ea9043ff3a37f9`, `751db49d9f2759ab`, `29f84859b8f378ac`, `4b3f6183b7d59b0c`.

- **Trip URL composition for present trip** (`toPush()`/`toEmail()` url concat around trip id; report UNTESTED ~65818, ~65854, ~65865).
  - Cause: missing positive assertion on `/trips/{id}` when trip exists left concat and empty-suffix mutants under-constrained.
  - Fix: `test_to_email_and_to_string_use_trip_destination_when_present` now also asserts push URL is exactly `/trips/{tripId}` (and email URL includes `{tripId}` under configured base URL).
  - Mutant IDs: `e0356e0f67ac7b8c`, `89b9f7e715196eb0`, `88f6049c673ca384`.

## `AutoCancelRequestIfRequestLimitedNotification` (`app/Notifications/AutoCancelRequestIfRequestLimitedNotification.php`)

- **Channels + payload metadata contract** (`via` list and `toEmail()` / `toPush()` envelope keys; report UNTESTED/UNCOVERED ~67585–67679).
  - Cause: existing tests validated destination text and fallback path, but did not pin channel entries and metadata keys, so `RemoveArrayItem` mutants on channels and payload metadata survived.
  - Fix: `Tests\Unit\Notifications\AutoCancelRequestIfRequestLimitedNotificationTest` now asserts exact `getVia()` channels, verifies `toEmail()` includes `name_app` and `domain`, and checks `toPush()` includes the fixed image key.
  - Mutant IDs: `3a0e29f299bf4eec`, `ed078bf12bc1e6e2`, `eb5973d5ba5e4600`, `e18a40ea1acabdd8`, `93c82203871a1c63`, `2200e75909509e85`.

- **Trip URL composition when trip exists** (`toPush()`/`toEmail()` URL concat around trip id; report UNTESTED ~67621, ~67657, ~67668).
  - Cause: no positive assertion for `/trips/{id}` left concat mutants (`ConcatRemoveRight` / `ConcatSwitchSides`) and empty-suffix mutation under-constrained.
  - Fix: `test_to_email_and_to_string_use_trip_destination_when_present` now asserts both email URL and push URL include the exact trip id under configured base URL.
  - Mutant IDs: `d6773c261f189342`, `6d6c1c4dd9b09a75`, `9c9e70bb13c4c552`.

## `FriendRequestNotification` (`app/Notifications/FriendRequestNotification.php`)

- **Delivery channels and envelope keys** (`via` list and payload metadata in `toEmail()` / `toPush()`; report RUN ~6307 and UNTESTED/UNCOVERED ~65887–65947).
  - Cause: previous tests covered title/message/url and sender id, but did not pin channel list and metadata keys, so `RemoveArrayItem` mutants on `via` channels and payload fields (`name_app`, `domain`, `image`) could survive.
  - Fix: `Tests\Unit\Notifications\FriendRequestNotificationTest` now asserts exact `getVia()` channels and verifies config-driven email metadata plus push image key while preserving existing behavioral assertions.
  - Mutant IDs: `daa7f4b4aaf97a99`, `20d83a74818297d7`, `e369cc1abcd9bd8d`, `3a70fab404df5b1b`, `e005b4fca5093951`, `e4edf96664a26b6d`.

## `RejectPassengerNotification` (`app/Notifications/RejectPassengerNotification.php`)

- **Channels + payload metadata contract** (`via` list and `toEmail()` / `toPush()` metadata keys; report RUN ~6375 and UNTESTED/UNCOVERED ~66043–66149).
  - Cause: previous tests validated reject title/message and trip id, but did not pin channel list and metadata envelope fields, so `RemoveArrayItem` mutants on channels and `name_app`/`domain`/`image` could survive.
  - Fix: `Tests\Unit\Notifications\RejectPassengerNotificationTest` now asserts exact `getVia()` channels, verifies config-driven email metadata (`name_app`, `domain`) and push image key, while keeping sender fallback behavior.
  - Mutant IDs: `1d72118965799cc8`, `9f1257bd3c1e1b5a`, `3eb7b469befce108`, `d880062ac589c8a6`, `a7b75e68cd90dca3`, `b53e3c9240091146`.

- **Trip URL composition on push/email** (`toEmail()` and `toPush()` concat around trip id; report UNTESTED ~66091, ~66127, ~66138).
  - Cause: no positive assertion for `/trips/{id}` left concat mutants on push URL and empty-suffix mutation under-constrained.
  - Fix: tests now assert exact email URL under configured base URL and exact push URL `/trips/{tripId}` when trip exists.
  - Mutant IDs: `05933aa25fa2cb52`, `048e163c3f682d59`, `13c00cbff5a42afa`.

## `AnnouncementNotification` (`app/Notifications/AnnouncementNotification.php`)

- **Channels + metadata envelope** (`via` and `toEmail()` metadata keys; report RUN ~6420 and UNTESTED/UNCOVERED ~66160–66230).
  - Cause: prior tests validated title/url/message behavior but did not pin the channel list and metadata keys, so `RemoveArrayItem` mutants on channels and email metadata (`name_app`, `domain`) survived.
  - Fix: `Tests\Unit\Notifications\AnnouncementNotificationTest` now asserts exact `getVia()` channels and verifies `toEmail()` includes config-driven `name_app` and `domain`.
  - Mutant IDs: `8e3fcd6232c8eae8`, `4f7afa8ba8b6f1dd`, `56e9c5a6d212af46`, `8d1e0c06fb899559`.

- **Push extras/image contract** (`toPush()` extras and image fields; report UNTESTED ~66208, ~66219, ~66230).
  - Cause: push assertions only checked message/title/url and `announcement_id`, leaving nested extras and image key removable without failing.
  - Fix: same test class now asserts `push.extras.type`, `push.extras.external_url`, and stable `push.image`.
  - Mutant IDs: `cb11e9770176ab0d`, `8f3dcc403c25ece1`, `ec0ac9fc9743bbb3`.

## `AutoRequestPassengerNotification` (`app/Notifications/AutoRequestPassengerNotification.php`)

- **Channels + email metadata contract** (`via` list and `toEmail()` metadata keys; report RUN ~6447 and UNTESTED/UNCOVERED ~66241–66335).
  - Cause: previous tests validated sender/trip message behavior, but did not pin channel list and email metadata fields, so `RemoveArrayItem` mutants on channels and `name_app`/`domain` survived.
  - Fix: `Tests\Unit\Notifications\AutoRequestPassengerNotificationTest` now asserts exact `getVia()` channels and verifies config-driven email metadata (`name_app`, `domain`).
  - Mutant IDs: `4a14ecb94567ecbd`, `30aea114a75ccf27`, `df492cd69fc409d3`, `2a6a0d4651081841`, `400033190345e0a5`.

- **Push URL/image contract for present and missing trip** (`toPush()` concat around trip id and image key; report UNTESTED ~66277, ~66313, ~66324, ~66335).
  - Cause: tests asserted fallback `/trips/` but not the positive `/trips/{id}` path nor image key, leaving concat and `RemoveArrayItem` mutants under-constrained.
  - Fix: same test class now asserts `toPush()` returns `/trips/{tripId}` when trip exists and always includes the static logo `image`.
  - Mutant IDs: `bdf3bee255fce6db`, `1b5fa90cd5b7cfa0`, `5b2170b6e88cb5b2`, `8740ad58c17a1584`.

## `SupportTicketReplyNotification` (`app/Notifications/SupportTicketReplyNotification.php`)

- **Delivery channels and push image contract** (`via` list and `toPush().image`; report RUN ~6489 and UNTESTED/UNCOVERED ~66346–66370).
  - Cause: existing tests covered message/url/type/extras but did not assert channel list and image key, so `RemoveArrayItem` mutants on `via` entries and push image survived.
  - Fix: `Tests\Unit\Notifications\SupportTicketReplyNotificationTest` now asserts exact `getVia()` channels (`DatabaseChannel`, `PushChannel`) and verifies `toPush()` includes the static logo image for both with/without ticket scenarios.
  - Mutant IDs: `bd55567746b8aae9`, `0a5247b11be9c61b`, `3f6015fb16429054`.

## `FriendAcceptNotification` (`app/Notifications/FriendAcceptNotification.php`)

- **Channels + email metadata contract** (`via` list and `toEmail()` metadata keys; report RUN ~6511 and UNTESTED/UNCOVERED ~66381–66441).
  - Cause: prior tests checked title/url and sender behavior but did not pin channel list and email metadata, so `RemoveArrayItem` mutants on channels and email fields (`name_app`, `domain`) survived.
  - Fix: `Tests\Unit\Notifications\FriendAcceptNotificationTest` now asserts exact `getVia()` channels and verifies `toEmail()` includes config-driven `name_app` and `domain` in addition to profile URL.
  - Mutant IDs: `2403bac287a55b68`, `c8f296031ecea1b6`, `8dfe7844f79c7a49`, `2eace53dd9439fe9`, `e1bcca36528f8b94`.

- **Push image contract** (`toPush().image`; report UNTESTED ~66441).
  - Cause: push tests validated message/url/extras but not image field, allowing `RemoveArrayItem` to survive.
  - Fix: same test class now asserts `toPush()` always includes the static logo `image`.
  - Mutant IDs: `0be549ff8070d6b3`.

## `CancelPassengerNotification` (`app/Notifications/CancelPassengerNotification.php`)

- **Channels + email metadata envelope** (`via` list and `toEmail()` metadata keys; report RUN ~6546 and UNTESTED/UNCOVERED ~66452–66524).
  - Cause: existing tests covered driver/passenger message branching and URL behavior, but did not pin channel list and email metadata fields, so `RemoveArrayItem` mutants on channels and email metadata survived.
  - Fix: `Tests\Unit\Notifications\CancelPassengerNotificationTest` now asserts exact `getVia()` channels and verifies `toEmail()` includes config-driven `name_app` and `domain` alongside title/url checks.
  - Mutant IDs: `1f9f8edb3d9f96ce`, `cb56ed4e464947d3`, `b901505cce66e7cd`, `ad547466f2b58ab9`, `f321351eb1bebca4`, `291cdef137ba14cd`.

- **Push image contract** (`toPush().image`; report UNTESTED ~66524).
  - Cause: push assertions focused on message/url/extras and missed image key.
  - Fix: same test class now asserts push payload always includes the static logo `image` for both trip-present and trip-missing flows.
  - Mutant IDs: `ba25913889089719`.

## `FriendRejectNotification` (`app/Notifications/FriendRejectNotification.php`)

- **Channels + email metadata contract** (`via` list and `toEmail()` metadata keys; report RUN ~6594 and UNTESTED/UNCOVERED ~66535–66595).
  - Cause: previous tests covered reject message/template fields and sender behavior, but did not pin channel list and email metadata (`name_app`, `domain`), so `RemoveArrayItem` mutants survived.
  - Fix: `Tests\Unit\Notifications\FriendRejectNotificationTest` now asserts exact `getVia()` channels and verifies config-driven `name_app` and `domain` on email payload.
  - Mutant IDs: `d3b1fbb9dcc7f93f`, `97cde90955901dd1`, `fd6bf38990757708`, `f3028b1fc3a34916`, `b2becdc8dc7eeabd`.

- **Push image contract** (`toPush().image`; report UNTESTED ~66595).
  - Cause: push tests validated message/url/extras id but not the image field.
  - Fix: same test class now asserts `toPush()` always includes the static logo `image`.
  - Mutant IDs: `0818175ba270b6b3`.

## `SubscriptionMatchNotification` (`app/Notifications/SubscriptionMatchNotification.php`)

- **Channels + email metadata envelope** (`via` list and `toEmail()` metadata keys; report RUN ~6624 and UNTESTED/UNCOVERED ~66606–66700).
  - Cause: previous tests covered title/message/url/extras basics but did not pin the channel list and email metadata keys, so `RemoveArrayItem` mutants on channels and `name_app`/`domain` survived.
  - Fix: `Tests\Unit\Notifications\SubscriptionMatchNotificationTest` now asserts exact `getVia()` channels and verifies config-driven `name_app` and `domain` in email payload.
  - Mutant IDs: `bb64a15df2c695c9`, `d868da8aaf64e741`, `e3981954c7d3801b`, `475a5ec9346bdfa1`, `16ff6ea87b7f88cd`.

- **Push URL/image contract** (`toPush()` concat around trip id and image key; report UNTESTED ~66642, ~66678, ~66689, ~66700).
  - Cause: tests validated fallback `/trips/` path but not positive `/trips/{id}` and image key, leaving concat and `RemoveArrayItem` mutants under-constrained.
  - Fix: same test class now asserts exact `/trips/{tripId}` when trip exists and always checks static logo `image`.
  - Mutant IDs: `f5d2e8602d41ff8d`, `0b61cbd3b87fd470`, `4f512b122933595c`, `aad9ebde1ddeee92`.

## `HourLeftNotification` (`app/Notifications/HourLeftNotification.php`)

- **Channels + email metadata envelope** (`via` list and `toEmail()` metadata keys; report RUN ~6658 and UNTESTED/UNCOVERED ~66711–66805).
  - Cause: previous tests validated destination messaging and basic URL behavior but did not pin channel list and email metadata (`name_app`, `domain`), so `RemoveArrayItem` mutants survived.
  - Fix: `Tests\Unit\Notifications\HourLeftNotificationTest` now asserts exact `getVia()` channels and verifies config-driven `name_app` and `domain` in email payload.
  - Mutant IDs: `4d9b507f82168236`, `5fd76c5a557088de`, `a4d7a8a2ced57e3c`, `0804806fa83891da`, `825484effd55b24d`.

- **Push URL/image contract** (`toPush()` concat around trip id and image key; report UNTESTED ~66747, ~66783, ~66794, ~66805).
  - Cause: tests asserted fallback `/trips/` and extras id but not positive `/trips/{id}` path nor image key, leaving concat and `RemoveArrayItem` mutants under-constrained.
  - Fix: same test class now asserts exact `/trips/{tripId}` when trip exists and always asserts static logo `image`.
  - Mutant IDs: `9b83aa339112ae21`, `2ed1f2778123fa5e`, `958b0222ec5f27ab`, `ecbd1c7fec372f06`.

## `NewMessagePushNotification` (`app/Notifications/NewMessagePushNotification.php`)

- **Channels + email metadata contract** (`via` list and `toEmail()` metadata fields; report RUN ~6700 and UNTESTED/UNCOVERED ~66816–66887).
  - Cause: previous tests covered sender/message behavior and conversation URLs, but did not pin channel list and email metadata keys, so `RemoveArrayItem` mutants on channels and `name_app`/`domain` survived.
  - Fix: `Tests\Unit\Notifications\NewMessagePushNotificationTest` now asserts exact `getVia()` channels and verifies config-driven email metadata (`name_app`, `domain`) along with conversation URL.
  - Mutant IDs: `e630a4ce294aaa38`, `cd6178de9b80e8b7`, `1993e96cf996ed83`, `590ac36f6c3f8a2b`.

- **Push type/image and empty-conversation contract** (`toPush()` fallback and payload keys; report UNTESTED ~66840, ~66876, ~66887).
  - Cause: tests validated push message/url/extras id but did not assert `type` and `image`, and did not explicitly lock empty-conversation fallback behavior under payload key removals.
  - Fix: same test class now asserts push `type` (`conversation`) and stable `image` in both fallback and populated cases, and keeps explicit checks for empty conversation id path `/conversations/`.
  - Mutant IDs: `eb9850c7d2c32ef8`, `9f17da8145b759fc`, `3f984caa601b356b`.

## `UpdateTripNotification` (`app/Notifications/UpdateTripNotification.php`)

- **Channels + email metadata contract** (`via` list and `toEmail()` metadata keys; report RUN ~6748 and UNTESTED/UNCOVERED ~66898–67004).
  - Cause: existing tests validated update message and trip URL behavior but did not pin channel list and email metadata fields, so `RemoveArrayItem` mutants on channels and `name_app`/`domain` survived.
  - Fix: `Tests\Unit\Notifications\UpdateTripNotificationTest` now asserts exact `getVia()` channels and verifies config-driven `name_app` and `domain` in email payload.
  - Mutant IDs: `5c26beda89778fc5`, `8404276b7ed8e91d`, `ed39f636ca804ed0`, `4b505887ef6a145d`, `c42fa5912850e475`.

- **Push URL/image and fallback trip-id contract** (`toPush()` concat around trip id and image key; report UNTESTED ~66934, ~66946, ~66982, ~66993, ~67004).
  - Cause: tests asserted fallback `/trips/` and extras id, but not positive `/trips/{id}` and image key in the same suite, leaving concat and payload-key mutants under-constrained.
  - Fix: same test class now asserts exact `/trips/{tripId}` when trip exists and always checks static logo `image`; fallback empty trip path remains explicitly checked.
  - Mutant IDs: `617d380e35055b64`, `3a937de282ba4924`, `664de3c8dc54d264`, `b4ad29d49eb5c02c`, `0ef7a1f027a9d62d`.

## `PendingRateNotification` (`app/Notifications/PendingRateNotification.php`)

- **Channels + email metadata contract** (`via` list and `toEmail()` metadata keys; report RUN ~6791 and UNTESTED/UNCOVERED ~67015–67063).
  - Cause: existing tests validated destination/title/url behavior, but did not pin channel list and email metadata fields (`name_app`, `domain`), so `RemoveArrayItem` mutants survived.
  - Fix: `Tests\Unit\Notifications\PendingRateNotificationTest` now asserts exact `getVia()` channels and verifies config-driven `name_app` and `domain` in email payload.
  - Mutant IDs: `11cee4fb3f0b0c2d`, `dea0f3f85ca80f3f`, `f1d94ffb399e8a59`, `768c5247f780bdc7`, `7ce14e22c467b772`.

## `RequestRemainderNotification` (`app/Notifications/RequestRemainderNotification.php`)

- **Channels + email metadata contract** (`via` list and `toEmail()` metadata keys; report RUN ~7057 and UNTESTED/UNCOVERED ~67690–67750).
  - Cause: existing tests covered title/url and message flow, but did not pin channel list and email metadata (`name_app`, `domain`), so `RemoveArrayItem` mutants survived.
  - Fix: `Tests\Unit\Notifications\RequestRemainderNotificationTest` now asserts exact `getVia()` channels and verifies config-driven `name_app` and `domain` in email payload.
  - Mutant IDs: `523e1a8313d09d7d`, `03c6d0248e7114fe`, `65f4cb260c1838db`, `fc6a1cfe780a0b55`, `9f5dc5a4f0ae2ad7`.

- **Push image contract** (`toPush().image`; report UNTESTED ~67750).
  - Cause: push assertions validated message/url/extras id but not the image field.
  - Fix: same test class now asserts `toPush()` always includes the static logo `image`.
  - Mutant IDs: `b7b0eb54270a7495`.

## `AcceptPassengerNotification` (`app/Notifications/AcceptPassengerNotification.php`)

- **Channels + email/push metadata contract** (`via` list, `toEmail()` metadata, and `toPush().image`; report RUN ~6814 and UNTESTED/UNCOVERED ~67075–67231).
  - Cause: previous tests validated message/url and `getExtras()` branching, but did not pin channel list plus metadata keys (`name_app`, `domain`, `image`), so `RemoveArrayItem` mutants on those payloads survived.
  - Fix: `Tests\Unit\Notifications\AcceptPassengerNotificationTest` now asserts exact `getVia()` channels, config-driven email metadata keys, and static push image in both present and fallback flows.
  - Mutant IDs: `4a12efbfa910faad`, `470e4f7fd1bba29e`, `10585b418562ac66`, `a645d18f25efcf9d`, `9ff998c76c09454b`, `c0d7babbc2413be8`.

- **`getExtras()` waiting-payment branch guard semantics** (`getExtras()` ~48–53; report UNTESTED ~67159–67219).
  - Cause: this method has compound guards (`is_object($to) && isset($to->id)`, request lookup, and waiting-payment state checks) where weak assertions can miss operator/equality mutants.
  - Fix: existing branch tests are kept behavior-first and explicit: no matching token request => `type=trip`; token user with `STATE_WAITING_PAYMENT` => `type=my-trips` and correct `trip_id`, which exercises object/id guard, state gate, and id propagation through the public payload.
  - Mutant IDs: `a5dbb5bd35d432b9`, `fdfb561b3809b6b1`, `f12e359ff30e9720`, `0dbb922329f39783`, `07d8ea2961b27a1d`, `0c0994b3db973a35`, `7ef3481701a3fa15`, `4caac72f000a9181`.

## `DeleteTripNotification` (`app/Notifications/DeleteTripNotification.php`)

- **Channels + email metadata contract** (`via` list and `toEmail()` metadata keys; report RUN ~6872 and UNTESTED/UNCOVERED ~67242–67348).
  - Cause: existing tests covered title/message/url and trip id, but did not pin channel list and email metadata keys (`name_app`, `domain`), so `RemoveArrayItem` mutants survived.
  - Fix: `Tests\Unit\Notifications\DeleteTripNotificationTest` now asserts exact `getVia()` channels and verifies config-driven `name_app`/`domain` fields in email payload.
  - Mutant IDs: `d4229e23b057541b`, `f99cb0def29c256b`, `fa0405ae6c7f66a3`, `4fd7258be3d49ced`, `ae1953b03beb54a0`.

- **Push URL/image and fallback trip-id contract** (`toPush()` concat around trip id and image key; report UNTESTED ~67278, ~67290, ~67326, ~67337, ~67348).
  - Cause: tests asserted fallback `/trips/` and extras id, but did not pin positive `/trips/{id}` and image key in the same suite, leaving concat and key-removal mutants under-constrained.
  - Fix: same test class now asserts exact `/trips/{tripId}` when trip exists and always checks static logo `image`, while preserving explicit fallback assertions.
  - Mutant IDs: `141adabfd3963efa`, `99e130ff74d5ad1f`, `241dda59fe2ab838`, `9e74badd1376c08f`, `212d0e94fa912e38`.

## `NewMessageNotification` (`app/Notifications/NewMessageNotification.php`)

- **Channels + email metadata contract** (`via` list and `toEmail()` metadata keys; report RUN ~6915 and UNTESTED/UNCOVERED ~67359–67454).
  - Cause: prior tests covered sender/conversation behavior, but did not pin channel list and email metadata keys (`name_app`, `domain`), so `RemoveArrayItem` mutants survived.
  - Fix: `Tests\Unit\Notifications\NewMessageNotificationTest` now asserts exact `getVia()` channels and verifies config-driven `name_app` and `domain` in email payload.
  - Mutant IDs: `81a545a25a6d7f8c`, `1c5caa8e9a9c0e29`, `921cd8c1ecb2ed3c`, `e6287ab0c842b5cc`, `3c6f0e5b09439cfd`.

- **Push type/image and empty-conversation fallback contract** (`toPush()` fallback + payload keys; report UNTESTED ~67395, ~67431, ~67443, ~67454).
  - Cause: tests validated message/url/extras id but did not pin push `type` and `image`, and needed explicit empty-conversation assertions to constrain fallback mutants.
  - Fix: same test class now asserts push `type` (`conversation`) and static `image` in both fallback and populated cases, keeping explicit `/conversations/` fallback behavior checks.
  - Mutant IDs: `a174e60ca2ab6723`, `8b08f5a36c8f657e`, `696fd8f6c68c78c4`, `ba9683b63103a225`.

## `NewUserNotification` (`app/Notifications/NewUserNotification.php`)

- **Mail-only channel and `force_email` contract** (`via` + `force_email`; report RUN ~6960 and UNTESTED/UNCOVERED ~67465–67501).
  - Cause: previous tests asserted `force_email` and URL behavior, but did not pin the explicit mail-only channel list, so channel-array removal mutants could survive.
  - Fix: `Tests\Unit\Notifications\NewUserNotificationTest` now asserts `getVia()` is exactly `[MailChannel::class]` and keeps `force_email === true` as a public contract.
  - Mutant IDs: `8c5555ea1fac3f38`, `273355d12ea614a6`.

- **Email metadata payload contract** (`toEmail()` `name_app`/`domain` fields; report UNTESTED ~67489, ~67501).
  - Cause: activation URL/title were asserted, but metadata fields were not pinned.
  - Fix: same test class now sets config and asserts `name_app` and `domain` in addition to activation URL behavior.
  - Mutant IDs: `9b540537bce228fa`, `84a60d616e6af51f`.

## `ResetPasswordNotification` (`app/Notifications/ResetPasswordNotification.php`)

- **Mail-only channel and `force_email` contract** (`via` + `force_email`; report RUN ~6979 and UNTESTED/UNCOVERED ~67513–67549).
  - Cause: tests asserted `force_email` and reset URL behavior, but did not pin the explicit mail-only channel list, so channel-array removal mutants could survive.
  - Fix: `Tests\Unit\Notifications\ResetPasswordNotificationTest` now asserts `getVia()` is exactly `[MailChannel::class]` and keeps `force_email === true` as a public contract.
  - Mutant IDs: `5be5c053131c8881`, `6bf9aec5eb95a4dc`.

- **Email metadata payload contract** (`toEmail()` `name_app`/`domain`; report UNTESTED ~67537, ~67549).
  - Cause: token/url/title fallback were asserted, but metadata fields were not pinned.
  - Fix: same test class now sets config and asserts `name_app` and `domain` along with reset URL/token behavior.
  - Mutant IDs: `eeb54f354e08d6d2`, `0ee36a74ac31f8dc`.

## `DummyNotification` (`app/Notifications/DummyNotification.php`)

- **Channel list contract** (`via` array; report RUN ~7003 and UNCOVERED ~67561–67573).
  - Cause: tests asserted email/text/extras behavior but did not pin the `via` array, so `RemoveArrayItem` mutants on `DatabaseChannel`/`MailChannel` survived.
  - Fix: `Tests\Unit\Notifications\DummyNotificationTest` now asserts `getVia()` is exactly `[DatabaseChannel::class, MailChannel::class]`.
  - Mutant IDs: `277a17cb78db227a`, `df82baf8af57be87`.

## `MercadoPagoService` (`app/Services/MercadoPagoService.php`)

- **Configuration guard behavior** (`__construct()` / `ensureConfigured()` / `createPaymentPreference()`; report RUN ~7187 and UNTESTED/UNCOVERED ~68931+).
  - Cause: service-level guard paths were not directly asserted, so mutants negating empty-token checks or removing required setup calls could survive.
  - Fix: `Tests\Unit\Services\MercadoPagoServiceTest::test_create_payment_preference_throws_when_access_token_is_missing` asserts the public exception contract when credentials are absent.
  - Mutant IDs: `9b1dc7339f43b267`, `4bdf596c8d58d9f2`, `37d074c750e714e1`, `fb8e31c0af73f9b7`, `ba057c55b7a4748a`, `30da4230fbc5121d`.

- **Sellado preference payload contract** (`createPaymentPreferenceForSellado()` path; report cluster around ~69087–69591).
  - Cause: URL assembly, amount conversion, and external reference generation were not asserted as public outputs, leaving payload-shape mutants under-constrained.
  - Fix: tests assert missing `frontend_url` throws; and with a test override of `createPaymentPreference`, assert generated back_urls, `unit_price` conversion from cents, `auto_return`, and hashed external reference shape.
  - Mutant IDs: `1244275cff538555`, `701d135546a19962`, `f1ab798f82a15e9b`, `5e304c8fe7b9d1d7`, `db359c3c6cd6c359`, `4f26b944579fabbb`, `4eabdfc07c637430`, `39ad6deaa20062f3`.

- **QR minimum-amount guard** (`createQrOrderForManualValidation()` lower bound; report cluster around ~70287+).
  - Cause: provider minimum (`>= 15.00`) guard was not pinned directly; comparison mutants could bypass payment constraints.
  - Fix: `test_create_qr_order_for_manual_validation_rejects_amount_below_provider_minimum` asserts an `InvalidArgumentException` for amounts below 1500 cents.
  - Mutant IDs: `9f2505c62c6495e6`, `11ba1d7bda32fe6a`, `c08b8c5f4036d0c8`, `51fa88af658de6ee`, `b5ae2dcba3ec93e8`.

## `AnnouncementService` (`app/Services/AnnouncementService.php`)

- **Default options and success stats contract** (`sendAnnouncement()` defaults/stats; report RUN ~7470 with UNTESTED mutations around ~20–31 and ~47).
  - Cause: tests covered only a subset of announcement flows, so defaults (`title`, batch/delay, retries/rate-limit) and response stats could be mutated without breaking assertions.
  - Fix: `Tests\Unit\Services\AnnouncementServiceTest::test_send_announcement_passes_default_options_to_send_to_user` uses a service subclass override of `sendToUser` to assert default options passed through and exact success stats envelope (`total/processed/successful/failed/skipped`).
  - Mutant IDs: `6aeac9a9d266229a`, `eb2f0587467b34aa`, `06fd7b745d8ec685`, `31f2c8e4debe2dba`, `a2b7698aef0acf9e`, `82c82fc5176a8483`, `d734d3b5a4e623db`, `daa2b8b9af52eb3a`, `cef7b03ba6060103`, `f588e39a0a595776`, `a1c5affa0a37c53e`, `e5ef7f7bec95c325`, `9b74f7681e68d27d`, `8518be05b62b4bbd`, `b189983beee3f2a9`, `bc996d596cc7d709`, `96a98668a1e05ee3`, `b285bef5eb64a260`, `3b45a75c4623f1d3`, `81d065c7555ad9c7`, `de4e5bc29a27407e`, `534ce82dc24b5c27`, `a25c5b860c895bba`, `febe4ec6cd3b54df`, `84694a55a759d426`, `3cdeae016beaa16b`, `b5ffce87a3506ee7`, `277c8fdd83118718`.

- **Active-only filtering and device-activity skip behavior** (`sendAnnouncement()` active_only gate + `sendToUser()` device_activity_days gate; report UNTESTED around ~34–38 and ~102–114).
  - Cause: guard branches for recent connections and recent device activity were not directly asserted, so branch/comparison mutants could survive.
  - Fix: `test_send_announcement_with_active_only_excludes_users_without_recent_connection` and `test_send_to_user_skips_when_only_stale_devices_exist_and_activity_filter_is_enabled` assert only recent users are processed and stale devices trigger the documented skip result.
  - Mutant IDs: `9870e81bb72d3621`, `425657d4c5a64367`, `a88ef40224d776c6`, `3755f7152a2d15b2`, `b350714ef7733c8e`, `e18e4e5b2a22e662`, `502685e33a249aa9`, `7a80b40cd13c1095`, `a923c148b89166fa`, `28c51f9998a4c9ec`, `540a5422b957720a`, `231278fcc0eb080e`, `3006a1f7a2470bd8`.

## `SupportTicketService` (`app/Services/SupportTicketService.php`)

- **Attachment iteration and path naming contract** (`storeReplyAttachments()` loop/folder/filename generation; report `RUN` ~7712 and UNTESTED cluster ~74153–74393).
  - Cause: tests did not enforce that invalid non-file entries are skipped (not terminating the loop) and that stored paths retain the `support/YYYY/MM/<ulid>_<random>.<ext>` contract, so loop-control and concat mutants could survive.
  - Fix: `test_store_reply_attachments_continues_after_invalid_item_and_processes_next_file` pins `continue` semantics when an invalid item appears first; `test_store_reply_attachments_persists_only_uploaded_files` now asserts a strict path regex that validates folder structure and generated filename shape.
  - Mutant IDs: `481fcda8e0c89b94`, `fc5b97260566e1e4`, `27d7fe2239ed486d`, `b22ece0363e1cbf9`, `a91a4fb4250d268b`, `6da2782b1f786a21`, `c1fa26dbe6162fdd`, `64073c2c4197432a`, `8a7e2040155411ab`, `6d8f84cd9e905cb6`, `32376970d735b086`, `8bfa85c810141ec1`, `87c76453a507775a`, `40b850aa96c9ce0b`, `b1308448cecd63aa`, `92929c3ddbeeefc9`, `5b1e99bb0c361256`, `68ccd1ac56409c1e`, `a8cd754db40e59f4`, `5e16982e9874d877`, `9c6f47aa00c970c9`.

- **Attachment metadata normalization** (`ticket_id`, mime fallback, size cast; report UNTESTED cluster ~74405–74453).
  - Cause: metadata assertions were partial, leaving room for mutants that remove `ticket_id`, bypass MIME fallback, or strip integer casting of file size.
  - Fix: expanded attachment assertions to pin `ticket_id = null`, exact MIME for regular uploads, integer `size_bytes`, and explicit `'application/octet-stream'` fallback when a file reports no MIME type.
  - Mutant IDs: `56295b30cdce490a`, `887972c9890360ba`, `f4820b7e07f13d46`, `736729407ea1265f`, `e951af3148175d79`.

## `ImageUploadValidator` (`app/Services/ImageUploadValidator.php`)

- **Max-size bytes fallback and numeric normalization** (`validate()` `maxBytes` initialization; report `RUN` ~7762 and UNTESTED line 18 cluster).
  - Cause: tests did not directly assert behavior when `image_upload_max_bytes` is absent or string-configured, leaving default-expression and cast arithmetic mutants under-constrained.
  - Fix: `test_default_max_size_is_ten_mb_when_config_is_missing` asserts 10 MB fallback output; `test_max_size_config_is_cast_to_integer_bytes_before_validation` asserts string config is interpreted as integer bytes and produces a stable 1 MB limit message.
  - Mutant IDs: `a2209fd09b739317`, `28c0bdda4631c783`, `cc18d4b85cc4b4d3`, `9c1bb0495ea70f03`, `e1e5f74c83f39e81`, `5be50bba52778139`, `673dc67339351329`, `ab5340d743546d56`, `0bc16fc0daa0dc93`.

- **Error-message precedence when multiple validations fail** (`validate()` coalescing on extension/size branches; report UNTESTED lines 31 and 35).
  - Cause: prior assertions did not pin that once a type error exists, extension and size checks must not overwrite that message; coalesce-removal mutants could silently change client-facing errors.
  - Fix: `test_existing_type_error_is_not_overwritten_by_extension_or_size_errors` uses a file that fails MIME, extension, and size simultaneously and asserts the first type error remains the returned message for the field.
  - Mutant IDs: `6ab86d936441fc55`, `a4b95665438fd324`, `77590d4dafe7a8e4`.

## `removeUserConversation` listener (`app/Listeners/Conversation/removeUserConversation.php`)

- **Who gets detached from the trip conversation** (`handle()` ~28–34; report `tests/coverage/20260428_2310.txt` ~5956–5961 and UNTESTED ~63839–63865).
  - Cause: `handle()` was not covered in isolation, so mutants could negate the `$trip->user_id == $event->from->id` ternary, swap `==` semantics, or strip `removeUser` without failing the suite.
  - Fix: `Tests\Unit\Listeners\Conversation\RemoveUserConversationListenerTest` uses a lightweight `stdClass` trip payload with a mocked `Conversation` plus a mocked `ConversationRepository`: no `conversation` ⇒ `removeUser` is never called; when the cancel `from` user is the trip owner, the **passenger** (`to`) is removed; when `from` is a third user (not the owner), **`from`** is removed—pinning both sides of the ternary and the equality check without relying on repository internals.
  - Mutant IDs: `bd7c2ab94c3c4c8b` (`TernaryNegated` ~32), `e5430e16ec1b974f` / `e7e1a2f0b702b383` (`EqualToNotEqual` / `EqualToIdentical` on the owner check ~32).

## `Dates` helpers (`app/Helpers/Dates.php`)

- **`parse_date` / `date_to_string` / `parse_boolean` return values** (`parse_date()` ~5–8, `date_to_string()` ~10–13, `parse_boolean()` ~15–18; report `tests/coverage/20260428_2310.txt` ~5966–5969 and UNTESTED ~63878–63904).
  - Cause: global helpers were never invoked from tests, so `AlwaysReturnNull` mutants on each `return` stayed green.
  - Fix: `Tests\Unit\Helpers\DatesHelperTest` (plain `PHPUnit\Framework\TestCase`, composer autoload only) asserts `parse_date` yields a `Carbon` instance for default and custom formats, `date_to_string` honors default and overridden formats, and `parse_boolean` matches `FILTER_VALIDATE_BOOLEAN` semantics for booleans and common string/integer inputs.
  - Mutant IDs: `34562addc83b7af7` (`parse_date` ~7), `7b45cc5826adac98` (`date_to_string` ~12), `d0d36613e717f4a0` (`parse_boolean` ~17).

## `Queries` helpers (`app/Helpers/Queries.php`)

- **`match_array` scalar/array contract** (`match_array()` ~5–8; report UNTESTED ~65213–65237).
  - Cause: helper was only used indirectly, so mutants could invert the ternary, drop the wrapped scalar element, or return null without a direct failure.
  - Fix: `Tests\Unit\Helpers\QueriesTest::test_match_array_keeps_arrays_and_wraps_scalars` asserts arrays are preserved and scalar input is wrapped as a one-item array.
  - Mutant IDs: `7d3c776dcf75f9fb`, `5446cf7fc9701b8f`, `56a09c36274f34e7`.

- **`make_pagination` default page + null-size behavior** (`make_pagination()` ~10–23; report UNTESTED/UNCOVERED ~65249–65357).
  - Cause: prior coverage only exercised one happy path and the null-size path once, leaving defaults (`$pageNumber`, `$pageSize`) and branching under-asserted.
  - Fix: tests assert paginated metadata for explicit page/size, implicit first page when page is omitted, and full collection return when `pageSize` is null.
  - Mutant IDs: `77545dd59c2e3a1d`, `32f8e0b230b37983`, `f2edd3082cce9a74`, `ee9938632436ab79`, `305f7314d2bcf2af`, `51cdc5d5ef65d4bf`, `a92eb851ecdfdca7`, `0c5999fe8ba56177`, `d02df906b2a441dc`, `18bab9b912252d5a`.

- **Query-log helpers** (`start_log_query()` / `stop_log_query()` / `get_query()` ~40–61; report UNCOVERED ~65443–65593).
  - Cause: helper trio had no direct assertions, so mutants could remove DB log toggles, break default-index selection, or change returned query+bindings string concatenation undetected.
  - Fix: tests execute real queries with logging enabled, assert `get_query()` returns latest/default and indexed entries with serialized bindings, then confirm `stop_log_query()` prevents later query capture.
  - Mutant IDs: `5583578a1672f7fe`, `1d61bbf8f6ae5be0`, `0b3f2006b446c90a`, `07b0165921fc1387`, `d593cc1897745b4e`, `5b3b2a1ed2038d59`, `103545b1dbbb78e8`, `b832d935011ee03f`, `cd679eeeb69abba2`, `d81cf3272b76c748`, `3c1ae3aa0cb7c3e8`, `20812a3f768098d0`, `1c8cb4223cb340f0`, `e4662202e9725faa`.

## `OldCordovaAppHelper` (`app/Helpers/OldCordovaAppHelper.php`)

- **`isOldCordovaApp()` UA / WebView detection** (`isOldCordovaApp()` ~12–24; report `tests/coverage/20260428_2310.txt` ~5971–5985 and UNTESTED ~63914–63926).
  - Cause: helper was never executed, so `BooleanAndToBooleanOr` on the Capacitor short-circuit (`! empty(X_APP_PLATFORM) && ! empty(X_APP_VERSION)`) and `FalseToTrue` on the WebView / Instagram guard could survive.
  - Fix: `Tests\Unit\Helpers\OldCordovaAppHelperTest` snapshots/restores `$_SERVER` around scenarios: missing `HTTP_SEC_CH_UA` / `HTTP_USER_AGENT` ⇒ `false`; both `HTTP_X_APP_PLATFORM` + `HTTP_X_APP_VERSION` set ⇒ `false`; only `HTTP_X_APP_VERSION` with WebView headers ⇒ `true` (proves both empties are required for the early exit); `Instagram` substring in `HTTP_USER_AGENT` ⇒ `false`; WebView `sec-ch-ua` without Instagram ⇒ `true`.

- **`getFakeTripData()` update-banner payload** (`getFakeTripData()` ~30–92; report UNTESTED ~63938–64830).
  - Cause: the fake trip envelope was never asserted, so `RemoveArrayItem`, integer nudges on zeros, `FalseToTrue` on boolean literals, and `EmptyStringToNotEmpty` on `request` stayed green.
  - Fix: same test class asserts stable Spanish banner strings, numeric counters, boolean flags, datetime-shaped `updated_at` / `last_connection`, and `assertArrayHasKey` over the full top-level and nested `user` key sets promised to API clients.
  - Mutant IDs: `d49ba8167ef10c8a` (`BooleanAndToBooleanOr` ~17), `ee1f1e9cba2f03d7` (`FalseToTrue` on WebView/Instagram line ~23); `getFakeTripData` cluster (representative IDs from the report listing): `92087f7c0588322d`, `9ae3fed2d63935a3`, `b93a1ce4d8856532`, `611fbb558182d263`, `48b0d1a589832710`, `70dd2b6177502360`, `c1d6e8b2f43ffa0a` (`EmptyStringToNotEmpty` ~88), `8b2f1c70ee7db199` — plus parallel `RemoveArrayItem` / `DecrementInteger` / `IncrementInteger` / `FalseToTrue` IDs on the same method through ~64830 in `tests/coverage/20260428_2310.txt`.

## `IdentityValidationHelper` (`app/Helpers/IdentityValidationHelper.php`)

- **Enforcement gating and config defaults** (`enforcementIsActive()` ~14–23, `isNewUserRequiringValidation()` ~58–80; report RUN ~6089+ and UNTESTED ~64841–65009).
  - Cause: prior tests mostly set explicit config values, so mutants that changed config defaults (`false` -> `true`) or removed early returns could survive when keys were absent.
  - Fix: `Tests\Unit\IdentityValidationHelperTest` now covers key-absence behavior by unsetting `carpoolear` keys and asserting the public policy outcome: enforcement is only active when enabled and not optional, and "required new users" is only enforced when the flag is truly present/enabled.
  - Mutant IDs: `b924252c1257bfea`, `aafc60cad9afc335`, `8aaa4d2bcfe1983d`, `b37ec49361d780e6`, `a7f3fc988e442dbb`, `7356795a65c6e5ce`, `b9dd022ccc45468a`, `1709889203edf6eb`.

- **Date cutoff and deadline semantics** (`newUsersCutoffDate()` ~31–36, `isUserCreatedOnOrAfterCutoff()` ~40–52, `isCurrentUserPastDeadline()` ~86–103; report UNCOVERED/UNTESTED ~64889–65129).
  - Cause: uncovered null/empty cutoff and deadline boundaries let early-return mutants survive (`return null/false` removals, inverted guards).
  - Fix: tests now assert null cutoff for empty config, parsed cutoff normalization to start-of-day, created-at before/after-cutoff behavior, and deadline boundary behavior with `Carbon::setTestNow` (same day allowed, after end-of-day blocked), including exemptions for new users and already validated users.
  - Mutant IDs: `bb9464df7094a716`, `b7bd82ec4f776e1f`, `b83b952b020b438c`, `2b95ceda6de2d03d`, `acd2b39bac8f6e5a`, `0e3cfd398df605d6`, `83795d01e3c14578`, `6fbb49f4311e3718`, `650c8d4c07f327fc`, `e8e019108181e78c`, `cacca3108609452d`, `48e74ec1a0fb1d1e`, `2debd42a9b54412a`.

- **Public error contract helpers** (`identityValidationRequiredError()` ~135, `identityValidationRequiredMessage()` ~139; report tail ~65177+).
  - Cause: helper return contracts were not asserted directly, so array/string return mutants could go unnoticed.
  - Fix: added direct contract assertions for the exact error shape expected by frontend (`identity_validation_required`) and stable user-facing message text.
  - Mutant IDs: `575359b26e8206d5`, `8420b4fc9e1d361f`, `d746cd304df7ec41`.

## DataController (`app/Http/Controllers/Api/v1/DataController.php`)

- **Constants `LIMIT_TOP` / `LIMIT_RANKING`** (lines ~12–13; report ~33792–33828, e.g. `0482c448462f2ca0` / `472a8f5bea6591ae` `DecrementInteger`/`IncrementInteger` on `25`, `c6a84f0b58a5c881` / `6feb9a501c1c567c` on `50`).
  - Cause: no HTTP or feature coverage executed `DataController`, so mutating those literals or the `LIMIT ?` bind positions never broke the suite.
  - Fix: `Tests\Feature\Http\DataApiTest` drives real SQL through `GET api/data/trips|seats|users|monthlyusers` and `GET /data-web`, exercising the bound limits on frequency and ranking queries indirectly (aggregate + seeded rows).

- **`trips()` / `seats()` / `users()` success JSON** (lines ~30, ~58, ~78; report ~33840–33900, e.g. `6335327c8ec74547`, `578355ca413649bd`, `14502503e2561085` `RemoveArrayItem` on `['trips'|'seats'|'users' => …]`, plus integer nudge mutants on the same `return response()->json` lines).
  - Cause: endpoints were never called under test; stripping the outer key or tweaking numeric fields in the payload could survive.
  - Fix: envelope checks (`assertJsonStructure`) plus behavioral assertions on grouped keys (`key`, month parts, counts, seat totals, passenger `state` / `estado`).

- **`monthlyUsers()` mapped rows** (lines ~93–97, ~101; report ~33948–34044, e.g. `cf8b6d1d65b568a6`, `a093e341a72fae00`, `7b79b6d7134d5250`, `2b398079e05822ce`, `d9652c8cd57f13b4`, `773db4fb059904a0` `RemoveArrayItem` on the `map` payload, and `742eb16c0427a15d` / `399a3bdea5957c38` on `sprintf` padding).
  - Cause: `ActiveUsersPerMonth` serialization was not asserted; mutants could drop `key`/`año`/`mes`/`cantidad`/`saved_at` or break zero-padding in `sprintf('%04d-%02d', …)` without failing tests.
  - Fix: `test_monthly_users_endpoint_returns_active_user_series_shape` seeds one row and asserts the public month key, numeric year/month/value, and presence of `saved_at`.

- **`data()` aggregate payload** (lines ~185–200, ~203; report ~34056–34238, e.g. `cb3657aa2fabe07d` through `7025ba8eee6799b2` on the large `response()->json([…])` and error envelope).
  - Cause: `data()` is only wired as `GET /data-web` in `routes/web.php`; it was not exercised, so `RemoveArrayItem` mutants on `usuarios`, `viajes`, `frecuencia_*`, `usuarios_activos`, etc. stayed UNCOVERED.
  - Fix: `test_data_web_returns_aggregate_dashboard_sections` hits `/data-web` with minimal trip/point/passenger/active-user fixtures and `assertJsonStructure` on every top-level section the API promises.

- **Follow-up:** `moreData()` (~207–253; report tail e.g. `422ac4184ade764a`, `2893731f1a287107`, `349bdb6c87db171d`, …) is not registered in `routes/api.php` / `routes/web.php` in this codebase, so it remains unreachable until a route or an explicit caller is added (or a dedicated unit test resolves the controller).

## MercadoPagoWebhookController (`app/Http/Controllers/Api/v1/MercadoPagoWebhookController.php`)

- **UNTESTED logging / constructor** (`handle()` ~31–47, `MercadoPagoConfig::setAccessToken`; report ~34350–34471, e.g. `a30885fa661110a0` / `72d3cbc178e0a2b9` `RemoveMethodCall`, `RemoveArrayItem` on the `Log::info` context arrays).
  - Cause: nothing hit `POST /webhooks/mercadopago`, so dropping logging or mutating log payloads never failed CI.
  - Fix: `Tests\Feature\Http\MercadoPagoWebhookTest` exercises the webhook route end-to-end; primary assertions target HTTP contracts (status + JSON), which pulls execution through `handle()` including the structured log call sites.

- **`payment.created` routing and verification** (~50–77; report ~34483–34783, e.g. `ce98aa85a01e0795` `RemoveEarlyReturn` on `order.processed`, `7524f29376f0d3ca` / `50e85c3812ed8f02` on `verifyMercadoPagoRequest`, `9f29cdb0064871ad` / `d41550cf99f2f008` on `400` payloads, `f9dd2a3f69450b38` on `getMercadoPagoPayment` null path, `9b4c6f3f2a03582d` on missing `data_id`).
  - Cause: no HTTP tests built valid `x-signature` / `x-request-id` / `data_id` manifests or simulated MP payment payloads, so negated guards and `RemoveArrayItem` on error JSON survived as UNCOVERED.
  - Fix: tests compute the same HMAC manifest as production (`id:{data_id};request-id:{x-request-id};ts:{ts};`), assert `400` for bad/missing verification, `500` with `Could not fetch payment` when the SDK layer throws (simulated via a stub `MPHttpClient`), and `200` `{"status":"success"}` for ignored actions (`action` ≠ `payment.created` / `order.processed`).

- **External reference branching** (~81–103; report ~34819–35035, e.g. `8361b02d89db3110` on `manual_validation:` / `manual_validation_`, `19d2a0007d479ab0` on `?? ''`, `c2c465d9100008fc` / `fa47f0a08a26a0ea` on `parseExternalReference`, `f86a23389af3be5d` on error JSON).
  - Cause: `payment.created` flow never reached `handleManualValidationPayment`, unknown-reference `400`, or trip sellado handling under test.
  - Fix: stubbed MP `GET /v1/payments/{id}` JSON returns `external_reference` for `manual_validation:{id}` (Checkout Pro), for an unknown string (`Unknown payment type`), and for a **hashed** `Sellado de Viaje ID: {tripId}` reference (plain text cannot be used because any `:` triggers the hash parser in `parseExternalReference`); DB assertions cover manual validation `paid` / `payment_id` and trip `payment_attempts` + `state`.

- **`order.processed` (QR / Orders API)** (`handleOrderProcessed()` ~458–506; report continues after `payment.created` cluster, e.g. `494c3d3e9d7dd3f7`-style branches on verification with `$isOrderWebhook = true`, payload `data.external_reference`, `processed` + `accredited`, and early `success` when not paid).
  - Cause: QR path was never executed with a valid signature and realistic body.
  - Fix: `orderProcessedSignatureHeaders()` uses `webhook_secret_qr_payment` and query `data.id` (lower-cased for the manifest, matching `verifyMercadoPagoRequest(..., true)`); `test_order_processed_for_manual_validation_qr_prefix_marks_paid_when_accredited` vs `test_order_processed_before_accredited_does_not_mark_manual_validation_paid` pin the paid / ignored outcomes.

- **Production fix (logging robustness):** `getMercadoPagoPayment()` catch previously called `$e->getApiResponse()` on every `Exception`, which fatals on generic errors (including test doubles). Guard with `method_exists($e, 'getApiResponse')` so non-MP exceptions still log and return `null` → `500` `Could not fetch payment` as intended.

## UserController (`app/Http/Controllers/Api/v1/UserController.php`)

- **Constructor middleware** (`__construct()` ~40–41; report ~39631–39727, e.g. `f0f1a167bb1bb670` / `bc1f67bdac8db97c` / `bfc6379d103427a6` `RemoveArrayItem` on `middleware('logged')->except([…])`, `7074fd4b08aae966` `RemoveMethodCall` on that `middleware`, parallel `RemoveArrayItem` / `RemoveMethodCall` IDs `6c46e9fb0ae93495`, `2d434692685868f1`, `402079dca80a6b71`, `322ff04769b7aaf2`, `1373d0a9692bd5a9` on `logged.optional` `only([…])`).
  - Cause: no feature tests hit `GET api/users/list` / `PUT api/users/` unauthenticated or public `bankData` / `terms`, so stripping middleware registrations or route exceptions never failed the suite.
  - Fix: `Tests\Feature\Http\UserControllerApiTest` asserts `401` + `Unauthorized.` for unauthenticated `list` and `PUT /`, and `200` public contracts for `GET api/users/bank-data` and `GET api/users/terms` (decoded JSON shape: bank/cc payload and terms `content`).

- **`create()` validated drivers + registration payload** (~51–92; report ~39739–39859, e.g. `f20dc2a17ea50569` `FalseToTrue` on `config('carpoolear.module_validated_drivers')`, `68370001f3c512d2` `TernaryNegated` on `auth()->user() ?: $user`, `c4051710e6d33ebf` `BooleanAndToBooleanOr` on `active && !banned`, `6324547d4936a8c7` / `ae04f3aeedac3b89` / `bd957c1c7a6d2b0b` on `$is_driver` in `update()` ~99).
  - Cause: registration and driver gating were not exercised end-to-end; mutants could flip config checks, viewer selection for `ProfileTransformer`, JWT issuance guard, or driver-intent boolean logic without detection.
  - Fix: guest `POST api/users/` asserts inactive signup has no top-level `token` and persists `active = 0`; authenticated inviter + registration asserts the new profile envelope omits `email` for a non-admin viewer (wrong `auth()->user() ?: $user` would expose it); `module_validated_drivers` true + fake image upload asserts `driver_data_docs` JSON is stored; `PUT api/users/` with `user_be_driver` and no docs returns `422` when the module is on, while a plain `description` update succeeds without driver intent; `module_validated_drivers` false is not combined with raw multipart driver files in tests (those files would still land in `$request->all()` and break persistence—behavior left to the “module on + upload” case).

- **`update()` banned-phone enforcement** (~106–118; report ~39871–40123, e.g. `f32e2f464723a5bc` / `e510e5d3cc128b3e` on `empty($banned_phones)`, `07a5d01ea28107cb` on `str_contains`, `62c2c3f528b47ec9` `BreakToContinue`, plus logging concat mutants on ~114).
  - Cause: the follow-up `UsersManager::update($profile, ['banned' => 1])` ran with `is_admin = false`, so `UserEditablePropertiesService::filterForUser` dropped `banned` and the ban never persisted—no test could observe the branch.
  - Fix: call `update($profile, ['banned' => 1], false, true)` so the ban is applied through the admin-allowlist path; `test_update_bans_user_when_mobile_matches_configured_fragment` sets `carpoolear.banned_phones` and asserts `users.banned` flips after a matching `mobile_phone` update.

- **`index()` / `searchUsers()` / `show()` / `badges()`** (~166–223; report ~40303–40447, e.g. `4de52af47ca5d8ee` / `7d858eba37fa268c` on `! ($id > 0)`, `5b1e135352c1f30c` / `321f8517c6d050b8` on `badges()` guards, plus `searchUsers` request `has('name')` / repository `if ($name)` coupling).
  - Cause: list/search/show/badges HTTP paths were not covered beyond ad-hoc flows, so comparison and collection-return mutants survived.
  - Fix: authenticated `GET api/users/list` with and without `value`, `GET api/users/search?name=…` (non-empty name so the repository query runs), `GET api/users/{id}` for another user, and `GET api/users/{id}/badges` with one visible and one hidden `Badge` attached—response must list only the visible badge title.

## NotificationController (`app/Http/Controllers/Api/v1/NotificationController.php`)

- **Constructor `logged` middleware** (`__construct()` ~18; report ~42039, e.g. `779c872fc3be09d4` `RemoveMethodCall`).
  - Cause: `GET|DELETE api/notifications*` was never called without a session/JWT fallback, so removing `middleware('logged')` left the suite green.
  - Fix: `Tests\Feature\Http\NotificationApiTest` asserts `401` + `Unauthorized.` on `GET api/notifications`, `GET api/notifications/count`, and `DELETE api/notifications/1` when unauthenticated.

- **`index()` JSON envelope** (~28; report ~42051–42063, e.g. `f69f1908891f8b37` `RemoveArrayItem`, `fbe364fa95eb8522` `AlwaysReturnNull` on `['data' => $notifications]`).
  - Cause: no HTTP test asserted the list contract returned by the controller (only manager/repository unit coverage).
  - Fix: authenticated `GET api/notifications` after a real `DummyNotification` is sent to the user; `assertJsonStructure` on `data[]` keys `id`, `readed`, `created_at`, `text`, `extras`, and `assertJsonPath` on the rendered `text` so stripping `data` or returning `null` fails.

- **`count()`** (~37; report `RUN` block ~3726–3727 already showed killed mutants for `RemoveArrayItem` / `AlwaysReturnNull` on the same envelope pattern).
  - Fix: same feature test asserts `GET api/notifications/count` returns `{"data":0}` with no rows, then `{"data":1}` after one unread notification is created—keeps the numeric envelope pinned without coupling to repository pagination.

- **`delete()` success JSON** (~48; report ~42075–42086, e.g. `01dac38bbba23c29` `RemoveArrayItem`, `4d1decfbc4f72942` `AlwaysReturnNull` on `['data' => 'ok']`).
  - Cause: `NotificationManager::delete()` returned the void result of `NotificationRepository::delete()`, so `$result` in the controller was always falsy even after a successful soft-delete; the action always threw `ExceptionWithErrors` and successful deletes were never observable under HTTP tests.
  - Fix: `NotificationManager::delete()` now returns `true` after persisting the soft delete and `false` when the row is missing or not owned; `Tests\Feature\Http\NotificationApiTest::test_delete_known_notification_returns_ok_envelope` asserts `DELETE api/notifications/{id}` → `200` + exact `{"data":"ok"}`; unknown id → `422` with the existing error message. `Tests\Unit\Services\Logic\NotificationManagerTest` expectations for the not-found / wrong-user paths were updated from `null` to `false`.

## NotificationManager (`app/Services/Logic/NotificationManager.php`)

- **`getNotifications()` unread-only regression protection** (`getNotifications()` around lines 22 and 24 in `tests/coverage/20260428_2310.txt` survivors).
  - Cause: existing tests validated shape, pagination, and mark-as-read behavior, but did not explicitly pin that listing endpoints return both read and unread notifications (the repository flag must stay `false` in both paginated and non-paginated branches).
  - Fix: added to `tests/Unit/Services/Logic/NotificationManagerTest.php`:
    - `test_get_notifications_returns_read_and_unread_rows_when_not_marking`
    - `test_paginated_get_notifications_returns_read_and_unread_rows`
    Both create read + unread rows and assert both `readed=true` and `readed=false` are present in the response.
  - Mutant IDs: `a4932146ea59090f`, `5a9ac6d79ce99892`.

## UsersManager (`app/Services/Logic/UsersManager.php`)

- **`create()` activation token contract + case-insensitive banned-name matching** (`create()` around lines 119 and 128 in `tests/coverage/20260428_2310.txt`).
  - Cause: prior tests checked activation-token presence and a lowercase banned-word config, but didn’t pin the token length contract (`Str::random(40)`) nor prove matching remains case-insensitive when banned words are configured with mixed casing.
  - Fix: added to `tests/Unit/Services/Logic/UsersManagerTest.php`:
    - strengthened `test_create_persists_inactive_user_and_dispatches_create_event` with `activation_token` exact length assertion (`40`).
    - `test_create_bans_user_when_banned_word_config_uses_mixed_case` to assert mixed-case configured banned words still ban lowercase user names.
  - Mutant IDs: `86174efcb6415a46`, `15a4ace8f553a316`, `26567b5534040daf`.

- **`resetPassword()` token and reset URL payload contract** (`resetPassword()` around lines 497 and 505 in `tests/coverage/20260428_2310.txt`).
  - Cause: tests asserted that an email job was queued, but didn’t lock token length or the exact reset URL passed into the queued job payload, allowing token-length and URL-concatenation mutants to survive.
  - Fix: strengthened `test_reset_password_queues_email_for_known_user` to assert:
    - returned token length is exactly `40`.
    - queued `SendPasswordResetEmail` contains the same token and the exact URL `${app.url}/app/reset-password/{token}` (via reflection over queued job payload).
  - Mutant IDs: `7b26e8eda8b83476`, `ce82a12ad7adc7f7`, `147c4727b1b15a2d`, `3241d1f1eb121787`, `8109b3665e672a30`, `28b0ab4513a7332e`, `6a0e9efd15a49350`, `b073b1a96c0c627a`.

## CarController (`app/Http/Controllers/Api/v1/CarController.php`)

- **Constructor `logged` middleware** (`__construct()` ~18; report ~42097, e.g. `9864f7b7934a7a50` `RemoveMethodCall`).
  - Cause: `tests/Feature/Http/CarApiTest.php` mocked `CarsManager`, so HTTP never exercised real middleware or persistence; dropping `middleware('logged')` stayed green.
  - Fix: integration-style `CarApiTest` asserts `401` + `Unauthorized.` for unauthenticated `GET|POST|PUT|DELETE api/cars` (and uses the real `CarsManager` / DB).

- **`create()` / `update()` / `show()` JSON envelopes** (~31, ~43, ~65; report ~42109–42145, e.g. `6246136888135c72` / `289bdccc04f8bce3` on `['data' => $car]`, `4b894bda8c4a9522` / `2ad05d8911e6c4f8` on the same pattern for `update`).
  - Cause: mocked manager bypassed `response()->json(['data' => …])`, so `RemoveArrayItem` / `AlwaysReturnNull` on the outer `data` key never broke CI.
  - Fix: `POST api/cars` and `PUT api/cars/{id}` assert `200`, `assertJsonStructure` on `data` (`id`, `patente`, `description`, `user_id`, `trips_count`), and `assertJsonPath` on fields returned to the client; `GET api/cars/{id}` asserts the same envelope for an owned car.

- **`delete()` success envelope** (~54; report ~42157–42169, e.g. `fd88d4b6a9b9b3b9` `RemoveArrayItem`, `082ba40b0ae75825` `AlwaysReturnNull` on `['data' => 'ok']`).
  - Cause: same mock-based coverage never asserted the literal success payload.
  - Fix: `DELETE api/cars/{id}` after a successful create → `200` + `assertExactJson(['data' => 'ok'])`, and the row is gone from the database.

- **`index()` return value** (~73; report ~42181, e.g. `37f8de8c792798f1` `AlwaysReturnNull`).
  - Cause: the mock returned a PHP array while production returns `$user->cars` (serialized as a top-level JSON array, not `{ data: … }`); `AlwaysReturnNull` was not exercised by HTTP.
  - Fix: `GET api/cars` with no cars → `[]`; with one persisted car for the user → non-empty JSON array and `patente` matches (separate tests avoid ordering the “empty index” call before inserts on the same authenticated `User` instance, which can leave a stale empty `cars` relation in memory).

- **Error paths** (`show` / `create` validation / duplicate car): `GET api/cars/{id}` for another user’s car or a missing id → `422` + `Could not found car.`; invalid body → `422`; second `POST` when `CarsManager` rejects duplicate ownership → `422`—these pin the existing `if (! $car)` / `if (! $result)` branches without coupling to internal error arrays beyond status and message.

## `CarsManager` (`app/Services/Logic/CarsManager.php`)

- **Duplicate-car error payload and scoped update uniqueness contract** (`create()` duplicate branch and `validator()` update rule composition; report `RUN` ~8323 survivors around lines 33/44).
  - Cause: tests asserted duplicate rejection but not the full error payload (`message` key), and did not explicitly assert update uniqueness still rejects a patente used by another car of the same user.
  - Fix: added assertions for duplicate error `message`, plus `test_validator_update_rejects_patente_used_by_same_user_other_car` to pin per-user scoped uniqueness during updates.
  - Mutant IDs: `bb0bf37a102a9f89`, `0ac9d6aa317d9728`.

- **Update/show/delete failure and ownership scalar-equivalence behavior** (`update()` validation-fail branch, `show()` owner comparison, `delete()` repo-fail / not-found branches; report survivors around lines 71/82/91/107/112).
  - Cause: prior tests covered happy paths and simple missing-id cases, but did not pin validation errors on update failures, value-based owner checks across scalar id types, or repository delete failure returning `can_delete_car`.
  - Fix: added tests for invalid update payload error propagation, owner check with equivalent `string`/`int` ids, and delete failure branch using a mocked repository that returns `false` from `delete()`.
  - Mutant IDs: `5c54c12ce1eae4e1`, `9e8b317e2ab7ec4f`, `a2323900408e799f`, `7eb659a30afcc28b`, `c4270b5c44a4b906`, `27d9a0e7423de0fe`.

## `FriendsManager` (`app/Services/Logic/FriendsManager.php`)

- **Pending cleanup before request/accept/reject/make transitions** (`request()`, `accept()`, `reject()`, `make()`; report `RUN` ~8371 survivors at lines 33–35, 52–53, 69, 99).
  - Cause: existing tests asserted final success states but did not explicitly pin that stale pending edges in both directions are deleted before creating new request/accepted states.
  - Fix: added tests that pre-seed opposite pending requests and assert pending rows are removed in the relevant direction(s) after `request`, `accept`, `reject`, and `make`, while preserving expected final friendship behavior.
  - Mutant IDs: `b3173ede283e1643`, `2d0953e958cf6cd5`, `2a7d621ea25aaaaa`, `8bfdb610196767e0`, `9d19f8f7c8bc331b`, `9f25c27e6d26842c`, `c2940e4641b4e040`.

## `PhoneVerificationManager` (`app/Services/Logic/PhoneVerificationManager.php`)

- **Send-code guard and validation error contracts** (`sendVerificationCode()` validator/blocked/cooldown branches; report `RUN` ~8435 survivors around lines 51–53, 72–75, 79–84).
  - Cause: tests covered successful send and some verification flows but did not explicitly pin send-path validation errors, blocked-pending behavior, or resend-cooldown rejection message behavior.
  - Fix: added tests for missing phone validation (`errors.phone`), blocked pending verification (`verification` blocked message), and cooldown enforcement (stable “Please wait …” message in `errors.verification`).
  - Mutant IDs: `dfcbe44aad70f46b`, `5994823fd95f55ee`, `e6a7310bcde4933d`, `f5c2c16da117868f`, `f33d106d256addf6`, `ee2443aff7ba4b8b`, `46cb92d4512b112a`, `680db3c20f00622d`, `7566d7280e4e5c06`, `ed07795a5c77a174`, `6d5eab04f1f202f0`.

- **Resend blocked path contract** (`resendVerificationCode()` blocked branch; report around lines 239–241).
  - Cause: resend coverage asserted “no pending verification” and successful resend, but not blocked-pending rejection semantics.
  - Fix: added `test_resend_verification_code_fails_when_pending_verification_is_blocked` to assert null result and the expected blocked error message.
  - Mutant IDs: `63cb1c49c702e5ac`, `0ce24ed886822c54`, `0c8b7e2940adf80a`.

## `RoutesManager` (`app/Services/Logic/RoutesManager.php`)

- **Fallback near-node threshold path in route matching** (`createRoute()` fallback condition around lines 101–105; report `RUN` ~8611 with UNCOVERED branch mutants at 102/105 and related threshold mutations).
  - Cause: existing route fixture tests exercised the main near-point branch, but not the fallback path (`md < 0.15 && d < 0.0125`) used when the main threshold fails for short segments.
  - Fix: `test_create_route_uses_fallback_near_point_threshold_branch` uses a short OSRM segment and a crafted nearby node so the primary condition fails while fallback thresholds pass, then asserts node attachment and processed route state.
  - Mutant IDs: `6f50d443d4c1e4c9`, `e2d8e33cb7619b29`, `7fe46d6b6de2f595`, `ba0f70226af82a9b`, `d0f2aea00f4b3b08`, `57692b1ddb2d83ab`, `b795a919fe3515f0`, `4385e95be85090a6`, `d1390b0a4203560f`, `19968d1f9c74ad5f`, `0e7645830bb44446`, `2bc7d3e750455ed6`, `4fd823e79d9bc309`.

## SocialController (`app/Http/Controllers/Api/v1/SocialController.php`)

- **Constructor middleware** (`__construct()` ~24–25; report ~42192–42216, e.g. `8ed44639c8d9f9bc` / `e0d9f7290643afcb` `RemoveArrayItem` / `RemoveMethodCall` on `middleware('logged')->except(['login'])`, `75cd57e29108ce0c` on `logged.optional` `only('login')`).
  - Cause: nothing hit `PUT api/social/update/{provider}` or `POST api/social/friends/{provider}` without auth, so removing `logged` or `logged.optional` never failed CI.
  - Fix: `Tests\Feature\Http\SocialApiTest` asserts `401` + `Unauthorized.` on `PUT api/social/update/test` and `POST api/social/friends/test` when unauthenticated.

- **`installProvider()` + container resolution** (~30–47; report ~42228–42360, e.g. `b65d1ac227139ef0` / `5c4a89dd27031ba2` on `strtolower`/`ucfirst`, concat mutants on the `STS\Services\Social\{Provider}SocialProvider` FQCN, `1060cde5f4429478` / `7bb040fbfa160916` on `App::when` / `App::bind`, `6a606c0269b28fd4` on `App::make`).
  - Cause: `STS\Contracts\Logic\Social` was never registered, and `App::bind('\STS\Contracts\SocialProvider', …)` used a string key that did not line up with the `SocialProvider::class` typehint Laravel uses when building `SocialManager`, so resolving the social stack failed and the `catch` returned `401` “provider not supported” for every request—including a valid test provider.
  - Fix: `AppServiceProvider` binds `STS\Contracts\Logic\Social` → `SocialManager`; the controller uses `SocialProvider::class` / `Social::class` for `bind`/`make`. `STS\Services\Social\TestSocialProvider` (URL segment `test`) supplies deterministic JSON-backed `access_token` payloads for HTTP tests without calling external OAuth APIs.

- **`login()` success and banned paths** (~48–68; report ~42360+ UNCOVERED, e.g. `fc9cbe71b582465b` / `db2a525be24a4094` on `if (! $user)`, `7724908c37db66f5` on `banned`, `4ba3273c61fea728` / `d6feaa8bdd68de97` on `['token' => …]`).
  - Cause: no end-to-end test exercised `POST api/social/login/{provider}` with a resolvable provider and linked account; the banned branch passed a scalar as `ExceptionWithErrors`’ second argument (not a usable errors payload).
  - Fix: feature tests create a `SocialAccount` for provider `test`, call `POST api/social/login/test` with a JSON `access_token`, assert a JWT-shaped `token` key for an active user, and assert `422` + `User banned.` + structured `errors` for a banned user. `gerErrors()` typos on `update`/`friends` were corrected to `getErrors()`.

- **`update()` / `friends()`** (~75–106; report ~42456–42516, e.g. `94b8ad80efa2fb1c` on `installProvider`, `b633099397824454` / `070c51a99d42a529` on `if (! $ret)` for `updateProfile`, parallel cluster for `makeFriends`).
  - Cause: authenticated paths were not covered under HTTP; unknown provider classes only raised `BindingResolutionException`, which was not caught (only `\ReflectionException`), so behaviour differed from the intended “provider not supported” handling.
  - Fix: tests assert `200` and JSON primitive `"OK"` for `update`/`friends` when the token matches the linked account; `catch (\ReflectionException|BindingResolutionException $e)` on `login` returns `401` JSON, and the same exception types on `update`/`friends` map to `ExceptionWithErrors('provider not supported')`. `TestSocialProvider::getUserData` forwards `description` when present so `updateProfile` can change an allowlisted field observable in the DB.

## `SocialManager` (`app/Services/Logic/SocialManager.php`)

- **Provider setup, update-validator rules, and existing-image preservation** (`__construct()`, `validator($data, $id)`, `loginOrCreate()`; report `RUN` ~7891 with UNTESTED at lines 33, 41, 57).
  - Cause: service-level tests did not explicitly pin constructor provider initialization, update-mode name-rule retention, or the branch that must avoid replacing an already present user image even when provider data contains a new image URL.
  - Fix: added tests asserting `setDefaultProvider()` is called in constructor, update validator still includes `name => max:255`, and `loginOrCreate()` does not call file creation / overwrite image when the linked user already has one.
  - Mutant IDs: `075b9332063a966c`, `745f9b6f8984eedb`, `4bb608b637a23b82`.

- **Ownership checks remain value-based across id scalar types** (`makeFriends()` and `updateProfile()` checks comparing authenticated user id with linked account user id; report UNTESTED at lines 73 and 83).
  - Cause: existing tests used same-type ids only; `==` to `===` mutants survived because no test exercised equivalent string/int ids that should still authorize the same user.
  - Fix: added tests with mocked social account payloads returning string ids while model users keep integer ids, asserting both friend sync and profile update still execute successfully.
  - Mutant IDs: `cbc29567bdec83cf`, `8a825a5de4a64e97`, `2676999b4e6fd963`.

## RatingController (`app/Http/Controllers/Api/v1/RatingController.php`)

- **Constructor middleware** (`__construct()` ~20–21; report ~42528–42576, e.g. `f39fad06752f81dc` / `0ff64d9344f5582f` `RemoveArrayItem` / `RemoveMethodCall` on `middleware('logged')->except([…])`, `d8e60e9f65d2233a` / `ad679d17dd3062d8` / `3db96a5488df3840` on `logged.optional` `only([…])`).
  - Cause: `tests/Feature/Http/RatingApiTest.php` mocked `RatingManager`, so unauthenticated requests never hit real `UserLoggin` / `AuthOptional` stacks; stripping `middleware('logged')` or `logged.optional` stayed green.
  - Fix: integration-style `RatingApiTest` calls `GET api/users/ratings?page_size=10` and `POST api/trips/{id}/reply/{user}` without auth → `401` + `Unauthorized.`; optional-auth routes are exercised with real `RatingManager` + DB.

- **`ratings()`** (~26–44; report ~42588, e.g. `d59dc33ce1bb6f08` `AlwaysReturnNull` on `paginator`).
  - Cause: mocked HTTP tests never asserted the Fractal paginated envelope for real `getRatings` + `RatingTransformer` output.
  - Fix: authenticated listing with `page_size` and a persisted `rating` row (`available = 1`, `user_id_to` = viewer) asserts a non-empty `data` array containing that rating’s `id`; `GET api/users/{id}/ratings?page_size=10` pins the “view another user’s ratings” branch (`UsersManager::show` + `getRatings`).

- **`pendingRate()`** (~47–63; report ~42600, e.g. `59d2813374437302` `AlwaysReturnNull` on `collection`).
  - Cause: guest path called `getPendingRatings($hash)` (expects a `User`), so hash-based pending mail links could not be covered reliably; `AlwaysReturnNull` on `collection()` was never tied to a real JSON body.
  - Fix: controller now calls `getPendingRatingsByHash($hash)` for guests; tests assert `GET api/users/ratings/pending` without auth → `422` `Hash not provided`, and `?hash=…` returns `200` with `data` containing the pending row.

- **`rate()` guest branch** (~66–86; report ~42612 `e0d0322692c526e3` `IfNegated` on `if ($me)`, plus ~42624–42636 `c4f5dbf6825f35cc` / `29c868d3c3af57c7` on the success JSON).
  - Cause: guest flow passed `$me` and the hash into `rateUser` in the wrong slots (`rateUser($me, $hash, …)`), so the hash never reached `getRate` as intended and the `if ($me)` branch vs `hash` branch was effectively untested under HTTP.
  - Fix: guest requests use `rateUser($hash, $userId, $tripId, $request->all())` (hash + rated user id + trip); tests assert `POST …/rate/{userId}?hash=…` → `200` + `{"data":"ok"}` and DB `voted`, and `POST` without hash → `422` `Hash not provided`.

- **`replay()`** (~89–101; report ~42648–42659, e.g. `915162bec6d4f9fd` `RemoveArrayItem`, `a39fcb3ea49ea6bf` `AlwaysReturnNull` on `['data' => 'ok']`).
  - Cause: mocked tests never asserted the literal success envelope or the authenticated-only gate beyond middleware.
  - Fix: `POST api/trips/{trip}/reply/{voter}` as the rated user persists `reply_comment` and returns exact `{"data":"ok"}`; a row that already has `reply_comment_created_at` → `422` `Could not replay user.`.

## RatingManager (`app/Services/Logic/RatingManager.php`) — supporting fixes for rating HTTP flow

- **`getRate()` hash path** (~36–55): in-memory `Collection::where('user_to_id', …)` used a **non-existent column name** (schema is `user_id_to`), so guest/hash resolution never matched a row; the path also risked reading `$rate->voted` when `$rate` was null.
  - Cause: behaviour could not match production DB; HTTP tests for guest rating stayed red until the query matched `user_id_to` and null was handled before property access.
  - Fix: resolve hash + trip + rated user via `Rating::query()->where('voted_hash', …)->where('user_id_to', …)` and early `return null` when no row exists; `Tests\Unit\Services\Logic\RatingManagerTest` now expects a returned `Rating` for a valid hash (replacing the old “throws on null” expectation) and adds `test_get_rate_with_hash_returns_null_when_no_row_matches`.

- **`activeRatings()` eligibility, deduplication, and canceled-state handling** (~123–170; report `tests/coverage/20260428_2310.txt` ~74633–74861).
  - Cause: existing tests only covered the simplest accepted-passenger path, so mutants in the trip filter criteria (`trip_date`, `mail_send`, `is_passenger`) and passenger-state gates (`STATE_ACCEPTED`/`STATE_CANCELED`, `CANCELED_REQUEST`, one rate pair per passenger user) could survive.
  - Fix: `test_active_ratings_processes_only_trips_matching_mail_send_is_passenger_and_date_filters` asserts that only eligible trips are processed and rated; `test_active_ratings_deduplicates_same_passenger_and_excludes_canceled_request_type` asserts duplicate passenger requests do not create duplicate pairs and cancellation-by-request is excluded from rating creation.
  - Mutant IDs: `8dfc9a886c44fc53`, `f573558a3e958412`, `ffa7e2e8873d636f`, `b060f7fcb689ee1c`, `b295ba698526c2bc`, `61680bdb609edd26`, `366d35caf3849d37`, `54d09bab22c07d84`, `9fa979558d0938d4`, `994d1ed0e66f649c`, `ead948fcb73cd717`, `5938ad1f1a26fc00`, `325cc5a32d994b4d`, `324a80a9167773d8`, `499336394d02eb4a`, `d893d9337e8d8d7a`, `01a0fac76e864d05`, `3545f95466d47ae8`, `80b63d6e0f2eb9d9`, `aa12e271766251b0`.

## ManualValidationPaymentController (`app/Http/Controllers/Api/v1/ManualValidationPaymentController.php`)

- **Mercado Pago redirect parameters** (`success()` ~17–21; report ~42670 `52e108ec7a1dbc2b` `TernaryNegated` on `payment_id` / `collection_id`).
  - Cause: only a generic redirect smoke hit `GET api/mercadopago/manual-validation-success` with no query string, so swapping the `?:` operands never broke CI.
  - Fix: `ManualValidationPaymentControllerTest` asserts `collection_id` is persisted when `payment_id` is absent, and `payment_id` wins when both are present.

- **Frontend base URL normalization** (`success()` ~22–23; report ~42682–42730 `5dba6d58746df5cf` `UnwrapRtrim`, `ff1b5f89c7e57334` / `568c26b481605596` / `a57c11da0880081f` `Concat*` on `$frontendBase` + path).
  - Cause: no assertion tied the configured base URL to the final `Location` header or to slash collapsing.
  - Fix: tests pin `config('services.mercadopago.oauth_frontend_redirect')` to a known origin, assert redirects to `{base}/setting/identity-validation/manual`, and assert a trailing slash on the base does not produce `//setting` in the URL.

- **`request_id` gate** (`success()` ~25; report ~42730 `5681f51465e95b14` `IfNegated` on `if ($requestId)`).
  - Cause: without a `request_id` query parameter, mutating the outer `if` never failed the suite.
  - Fix: `test_without_request_id_redirects_to_manual_validation_route` expects the base manual-validation path with no `request_id` query segment.

- **Persist only when row exists and result is success** (`success()` ~27–28; report ~42742–42778 `4d5cf8ad378f4245` `IfNegated`, `29f11d10045393bf` / `61fa29ac3708cef7` on `=== 'success'`, `436e8462f7c5392b` `TrueToFalse` on `paid`).
  - Cause: no HTTP test created a `ManualIdentityValidation` and then exercised failure vs success `result`, so negated guards or flipped booleans on `paid` stayed green.
  - Fix: success path asserts `paid`, `paid_at`, optional `payment_id`; `result=failure` asserts `paid` stays false and `payment_result` appears on the redirect URL instead of `payment_success`.

- **Optional `payment_id` assignment** (`success()` ~30–32; report ~42790–42814 `2c0e82621374f7f4`, `9f9e171ff4ec26dc`, `dc9a0b844536448c`, `0e809fec086bd1d8`, `5fb25ef74ecd198b`, `154bd897691adca7`, plus `RemoveMethodCall` on `save` / `Log::info` ~42826–42874).
  - Cause: nothing asserted that empty/absent payment identifiers leave `payment_id` null while a concrete id is stored when provided.
  - Fix: one test calls success with no `payment_id`/`collection_id` and expects `payment_id` remains null; others cover `mp-…` / `col-…` persistence; `Log::spy()` asserts the structured `Manual identity validation payment success` log on the happy path.

- **Redirect query assembly** (`success()` ~40–44; report ~42826+ `RemoveArrayItem` / `Concat*` / `IfNegated` on `payment_success` vs `payment_result`, e.g. `639feeeb046cfce5`, `69bf21bfd77cab86`, `28ee521144e5d306`, `278a09704c38443e`).
  - Cause: redirect URL fragments were not parsed or asserted, so concat/order mutants on `request_id`, `payment_success=1`, and `payment_result` survived.
  - Fix: tests parse `Location` with `parse_url` / `parse_str` and assert `request_id`, `payment_success=1` on success, `payment_result` on non-success, and an unknown numeric `request_id` still yields a redirect without creating a DB row.

## RoutesController (`app/Http/Controllers/Api/v1/RoutesController.php`)

- **Constructor middleware** (`__construct()` ~14–16; report ~43068–43092 `3287c71431a84354` / `f3df9f4d30bca0e3` `RemoveArrayItem`, `d2d0058f294666c0` `RemoveMethodCall` on `middleware('logged')->except(['autocomplete'])` and `logged.optional` `only('autocomplete')`).
  - Cause: nothing hit `GET api/trips/autocomplete` with the real controller stack, so stripping middleware registrations never failed CI.
  - Fix: `RoutesApiTest` exercises `GET api/trips/autocomplete` without auth and asserts `200` + JSON (confirms `logged.optional` path); the controller still registers strict `logged` for any future non-autocomplete actions.

- **Default country when omitted** (`autocomplete()` ~22–24; report ~43104–43116 `7526843b114dac91` `IfNegated`, `8b9dda63c478d5fc` `RemoveNot` on `isset($data['country'])`).
  - Cause: no HTTP request asserted behaviour when `country` was missing from the query string.
  - Fix: seed a `NodeGeo` with `country = ARG`, call autocomplete with `name` + `multicountry` only, and assert the first hit stays in `ARG`.

- **Search gate and multicountry flag** (`autocomplete()` ~25–29; report ~43128–43183 `aaabb5d8da0db706`, `dfca7ecbdec0485a`, `942d66eed2738eed`, `e15d928b6b9ed73b` on `isset`/`=== 'true'`, plus ~43183 `5f7f84d37b3b46b6` `RemoveArrayItem` on the JSON envelope).
  - Cause: only repository/unit coverage existed; HTTP never compared `multicountry=true` vs `false` or asserted the `nodes_geos` key.
  - Fix: feature tests seed distinct `nodes_geo` rows and assert country filtering when `multicountry=false`, cross-country results when `multicountry=true`, literal `'false'` vs `'true'` semantics, and `assertJsonStructure` on `nodes_geos`.

- **Missing required query parameters** (implicit fall-through after ~25–31; same report block as line ~29 `RemoveArrayItem` on the success response).
  - Cause: `autocomplete()` returned `null` when `name` or `multicountry` was absent, so clients saw an empty/non-JSON body and mutants on the `response()->json([…])` array could not be killed from HTTP.
  - Fix: controller now returns `200` + `{"nodes_geos":[]}` when required inputs are missing; `test_autocomplete_without_required_query_returns_empty_nodes` pins that contract.

## AuthController (`app/Http/Controllers/Api/v1/AuthController.php`)

- **Constructor `logged` middleware** (`__construct()` ~28; report ~43195–43231 `e00e4f81a6bf2df1` / `ce0bb53f780c5983` `RemoveArrayItem`, `5728fb0b63843b2c` `RemoveMethodCall` on `middleware('logged')->only(['logout', 'retoken'])`).
  - Cause: `Tests\Feature\ApiAuthTest` and similar suites mocked `UsersManager` / JWT for several flows, so stripping `middleware('logged')` on `logout`/`retoken` never failed CI.
  - Fix: `AuthControllerApiTest` calls `POST api/logout` and `POST api/retoken` without a bearer token → `401` + `Unauthorized.`; authenticated `logout`/`retoken` hit the real stack.

- **`_getConfig()` donation / banner / Cordova ternaries** (~34–48; report ~43243–43279 `8422e52f9a6d765d` `FalseToTrue` on `$isCordova`, `ab57889e92d7181a` / `105749d67085b274` / `66a6aae3113a9baa` / `3dce1227c281770f` `TernaryNegated` on banner fields, plus `RemoveArrayItem` clusters ~43291–43379 on the `$exclude` list and merged keys).
  - Cause: no HTTP test asserted `GET api/config` against real `config('carpoolear')` values or the Cordova vs default banner selection.
  - Fix: public `GET api/config` asserts nested `donation` numerics match config, `qr_payment_pos_external_id` stays off the payload, and a WebView-style `$_SERVER` fingerprint (as used by `OldCordovaAppHelper`) switches `banner.url` to the Cordova URL when those config entries differ.

- **`_getConfig()` merge loops and manual QR gate** (~60–79; report ~43402–43582 `6bada9b3a1fef48a` on `unset`, `ForeachEmptyIterable` ~43486–43498, `6f0dd83eb1e894a1` / `b5bab155d21ccea4` / `718863c38c065038` / `a59f957ce24f60b2` / `dc0a4ab1026cfd20` / `4337f303cdde8aea` on the `identity_validation_manual_qr_enabled` boolean chain).
  - Cause: config merge and QR-enabled boolean were only exercised indirectly; empty `$exclude` / broken `foreach` could survive without a full config response assertion.
  - Fix: `identity_validation_manual_qr_enabled` is asserted to be a boolean on every `GET api/config` response (computed from live config + Mercado Pago settings).

- **`getConfig()` authenticated branch** (~84–97; report ~43582+ `2720a11b8a0c322f` `IfNegated` on `if ($user)`, `19b7ae4662ddd5b2` on `Log::warning`, `6bef5867dc648a86` on `response()->json`).
  - Cause: nothing hit `getConfig` with and without an authenticated user to pin the warning-only branch vs identical JSON output.
  - Fix: primary contract test uses the public route without auth (still `200`); optional-auth behaviour is covered by the same response shape so regressions in `response()->json($this->_getConfig(...))` fail the suite.

- **`login()`** (~100–127; report ~43618+ `314dd90b5e543cab` / `944849ca58487447` / `37be98846994100c` / `0eb34fa290ed8473` on JWT attempt, plus JSON keys `token` / `config` ~43726–43738).
  - Cause: mocked or happy-path-only login never asserted `401` `invalid_credentials`, banned/inactive `401`s, or the `{ token, config }` envelope.
  - Fix: integration tests with `User::factory()` cover bad password, `banned`, `inactive`, and successful login with `assertJsonStructure(['token','config'])`.

- **`retoken()`** (~130–173; report ~43666–43702 and `RemoveEarlyReturn` / array mutants on the success payloads).
  - Cause: `ApiAuthTest::testRetoken` mocked JWT and `DeviceManager`, so real `JWTAuth::getToken` / refresh paths were not exercised from HTTP.
  - Fix: missing bearer → `401`; after real `login`, `POST api/retoken` with `Authorization: Bearer …` → `200` and `token` + `config` keys.

- **`logout()`** (~176–199; report ~43822–43966 on `invalidate`, `Log::info` / `Log::error`, and the `'OK'` payload).
  - Cause: no feature test asserted `POST api/logout` without auth vs with a freshly issued JWT, or the string `"OK"` body.
  - Fix: unauthenticated `logout` → `401`; authenticated `logout` → `200` and response body contains `OK`.

- **`reset()` / `changePasswod()` / `active()` / `log()`** (~213–266; report ~43714–44266 on validation, `ExceptionWithErrors`, queue side-effects, `changePassword` success JSON, `active` token issuance, and `log()` return).
  - Cause: password reset / change / activation were mostly covered with `UsersManager` mocks or skipped validation and queue assertions.
  - Fix: `POST api/reset-password` asserts `422` on missing `email`, `422` + `user_not_found` for unknown addresses, `{"data":"ok"}` + `Queue::assertPushed(SendPasswordResetEmail::class)` for real users (throttle middleware disabled in this test class only); `POST api/change-password/{token}` follows a real reset row and verifies the password hash updates; `POST api/activate/{token}` covers invalid vs valid activation tokens; `POST api/log` asserts `200`.

## SubscriptionController (`app/Http/Controllers/Api/v1/SubscriptionController.php`)

- **Constructor `logged` middleware** (`__construct()` ~17; report ~44277 `5d29aa8234bb5792` `RemoveMethodCall`).
  - Cause: happy-path subscription tests authenticated first, so removing `middleware('logged')` never broke CI if nothing asserted unauthenticated `GET|POST|PUT|DELETE api/subscriptions*`.
  - Fix: `SubscriptionsApiTest::test_subscription_endpoints_require_authentication` now hits `GET api/subscriptions`, `GET api/subscriptions/{id}`, `POST`, `PUT`, and `DELETE` without auth and expects `401` + `Unauthorized.` on each.

- **`create()` JSON envelope** (`create()` ~25–30; report ~44289–44301 `3d136cad6b2c42dd` `RemoveArrayItem`, `0210d42bec609bd0` `AlwaysReturnNull` on `['data' => $model]`).
  - Cause: success path was covered, but duplicate / validation failures did not pin the `422` + `Could not create new model.` contract when `SubscriptionsManager::create` returns falsy.
  - Fix: `test_create_duplicate_geometry_returns_unprocessable` posts the same valid geometry twice for one user and asserts `422` + message; invalid body still returns `422` with `errors`.

- **`update()` JSON envelope and ownership** (`update()` ~33–42; report ~44313–44325 `889a361f5f558540`, `d7f5005e2e783a35`).
  - Cause: only the owned update path was exercised; mutants on `response()->json(['data' => $model])` or skipping the `if (! $model)` guard stayed green.
  - Fix: `test_update_returns_unprocessable_when_subscription_not_owned` and `test_update_returns_unprocessable_when_validation_fails` assert `422` with `Could not update model.` vs validation `errors` respectively.

- **`delete()` success and error envelopes** (`delete()` ~45–53; report ~44337–44349 `9f81ffbda5a22c2f`, `37d088fcab7e2292`).
  - Cause: only successful delete asserted `{"data":"ok"}`; cross-user delete did not exercise `ExceptionWithErrors('Could not delete subscription.', …)`.
  - Fix: `test_delete_returns_unprocessable_when_subscription_not_owned` expects `422` + `Could not delete subscription.`; success test keeps `assertExactJson(['data' => 'ok'])`.

- **`show()` ownership gate** (`show()` ~56–64; report ~44361–44372 `b736079953f89cd7`, `84645031db77ee06`).
  - Cause: `show` for another user’s id was not asserted, so `AlwaysReturnNull` on the success JSON or inverted ownership checks could survive.
  - Fix: `test_show_returns_unprocessable_when_subscription_belongs_to_another_user` asserts `422` + `Could not found model.`.

- **`index()` list envelope** (`index()` ~67–72; report ~44361–44372 `b736079953f89cd7` `RemoveArrayItem`, `84645031db77ee06` `AlwaysReturnNull` on `['data' => $models]`).
  - Cause: index was only asserted for inclusion of an active row, not as an explicit `{ data: … }` envelope when the list is empty.
  - Fix: authenticated `GET api/subscriptions` remains `200` with a `data` array (`test_index_returns_active_subscriptions_for_user`); unauthenticated access is rejected by the strengthened auth test.

## ManualIdentityValidation (`app/Models/ManualIdentityValidation.php`)

- **Mass-assignment contract for manual validation lifecycle fields** (`$fillable` lines 17–29 in `tests/coverage/20260428_2310.txt`).
  - Cause: model tests covered relations/casts/helpers but didn’t pin the exact fillable surface; removing any fillable key could survive if tests only touched a subset of attributes.
  - Fix: added to `tests/Unit/Models/ManualIdentityValidationTest.php`:
    - `test_fillable_contains_expected_mass_assignable_attributes`
    - `test_mass_assignment_persists_all_review_and_payment_fields`
    These assert both the full fillable list and real `create()` persistence for payment/review/manual-start fields.
  - Mutant IDs: `84f43907322f962b`, `4705b6263166b099`, `3cb08e07de73c1cc`, `6e62d8422a8d839c`, `7d8adabe27659fe4`, `4ba4552021d52953`, `79835424ac10bb8f`, `e58dad87422beaa7`, `f2aa4b472624b662`, `794c4a564fba43ee`, `0a81e52988778478`, `e6bc411f37c6bdec`, `3dbd099e62abc909`.

## Donation (`app/Models/Donation.php`)

- **Mass-assignment contract for donation rows** (`$fillable` lines 12–16 in `tests/coverage/20260428_2310.txt`).
  - Cause: model tests validated casts and selected persistence behavior, but they didn’t pin the full fillable list; `RemoveArrayItem` mutants on `$fillable` survived.
  - Fix: added to `tests/Unit/Models/DonationTest.php`:
    - `test_fillable_contains_expected_mass_assignable_attributes`
    - `test_mass_assignment_persists_all_fillable_fields`
    These assert the exact fillable contract and verify all fillable keys persist through `create()`.
  - Mutant IDs: `d8af4dcd72dfc2b2`, `d771b8f164b719b9`, `1f5f76febfc98389`, `89a4678326e89709`, `70d3001e0fbc3550`.

## Badge (`app/Models/Badge.php`)

- **Mass-assignment contract for badge definition fields** (`$fillable` lines 11–16 in `tests/coverage/20260428_2310.txt`).
  - Cause: existing tests covered casts and relationships, but they didn’t pin the complete fillable contract; `RemoveArrayItem` mutants on badge attributes survived.
  - Fix: added to `tests/Unit/Models/BadgeTest.php`:
    - `test_fillable_contains_expected_mass_assignable_attributes`
    - `test_mass_assignment_persists_all_fillable_fields`
    These lock the explicit fillable list and verify all badge definition fields persist via `create()`.
  - Mutant IDs: `e2fa699671b0aa92`, `baf6bc9ab402982a`, `3cf19009af3ecf9a`, `7a66c0e065209a7c`, `dcbe52a12ffd3217`, `644369ccdea1202f`.

## Rating (`app/Models/Rating.php`)

- **Factory and constants contract for rating model** (`newFactory()` line 15 and constants lines 17/19/21 in `tests/coverage/20260428_2310.txt`).
  - Cause: existing relationship/cast tests did not directly pin the factory override contract, leaving `AlwaysReturnNull` on `newFactory()` under-constrained.
  - Fix: added `test_model_uses_rating_factory` in `tests/Unit/Models/RatingTest.php`, invoking `newFactory()` via reflection and asserting it returns `Database\Factories\RatingFactory`.
  - Mutant IDs: `cd9f456db729ff8e`.

- **Mass-assignment contract for rating payload fields** (`$fillable` lines 26–37 in `tests/coverage/20260428_2310.txt`).
  - Cause: tests created ratings through factories but did not lock the explicit fillable list, so `RemoveArrayItem` mutants on rating fields survived.
  - Fix: added `test_fillable_contains_expected_mass_assignable_attributes` in `tests/Unit/Models/RatingTest.php` to assert the full fillable list.
  - Mutant IDs: `d1f82b51ff3b844c`, `d40f1893aa197f2c`, `3ca7432a86d5423a`, `7296c5e8f4ef9143`, `c0c0f8639e284fb0`, `b87bd2670900102f`, `f903d44decd8f705`, `a1dd123907df6982`, `25052a0138f3b66b`, `46aca11abc0af2f4`, `b9fdb174c2df2bcd`, `50abbf7f635b94e3`.

## Message (`app/Models/Message.php`)

- **Mass-assignment contract for message payload fields** (`$fillable` lines 16–21 in `tests/coverage/20260428_2310.txt`).
  - Cause: existing tests already covered relationships, casts, touches and read counting, but they did not explicitly lock the full fillable list; `RemoveArrayItem` mutants on message fields remained under-constrained.
  - Fix: added `test_fillable_contains_expected_mass_assignable_attributes` in `tests/Unit/Models/MessageTest.php` to assert the full fillable contract.
  - Mutant IDs: `f60c93c13f8b3aa0`, `85bc7ccf0a366016`, `ab56e01336a65e24`, `ae4b7cef01a92bb5`, `3a47d9dcd297ef5b`, `6a572d38393973bf`.

## RouteCache (`app/Models/RouteCache.php`)

- **Mass-assignment and cast contract for cached routes** (`$fillable` lines 12–15 and `$casts` lines 19–21 in `tests/coverage/20260428_2310.txt`).
  - Cause: behavioral tests validated hashing/update flows but did not pin the explicit fillable and cast declarations directly, so `RemoveArrayItem` mutants in both arrays survived.
  - Fix: added to `tests/Unit/Models/RouteCacheTest.php`:
    - `test_fillable_contains_expected_mass_assignable_attributes`
    - `test_casts_include_points_route_data_and_expires_at`
    These assert the exact fillable list and required casts (`points`, `route_data`, `expires_at`).
  - Mutant IDs: `337fe7413e2924ba`, `0357ec5a936b9e17`, `a203dddbdb2afa94`, `ae712c541080994a`, `4d3d9d3cb51614c1`, `4b787bf9379b0f5d`, `47ba9c07ca364dfe`.

## DeleteAccountRequest (`app/Models/DeleteAccountRequest.php`)

- **Mass-assignment contract for delete-request lifecycle fields** (`$fillable` lines 16–19 in `tests/coverage/20260428_2310.txt`).
  - Cause: tests already pinned action constants and datetime casts, but they did not explicitly lock the full fillable list, so `RemoveArrayItem` mutants on request payload fields survived.
  - Fix: added `test_fillable_contains_expected_mass_assignable_attributes` in `tests/Unit/Models/DeleteAccountRequestTest.php` to assert the complete fillable contract.
  - Mutant IDs: `4e426c4b533f019c`, `99d2660358954bb2`, `741510b11a90a082`, `80be6b480e417b75`.

## SocialAccount (`app/Models/SocialAccount.php`)

- **Mass-assignment and hidden-fields contract for social identities** (`$fillable` line 10 and `$hidden` line 13 in `tests/coverage/20260428_2310.txt`).
  - Cause: existing tests asserted relation and serialization output but did not directly lock the declared fillable/hidden arrays, so `RemoveArrayItem` mutants on those contracts survived.
  - Fix: added to `tests/Unit/Models/SocialAccountTest.php`:
    - `test_fillable_contains_expected_mass_assignable_attributes`
    - `test_hidden_contains_created_at_and_updated_at`
    These explicitly pin both model contracts.
  - Mutant IDs: `376096f277675b26`, `a70b30eca57d2673`, `6fb088ec974b9202`, `1b4f71927431e9f9`, `d975ca07f581acdc`.

## TripVisibility (`app/Models/TripVisibility.php`)

- **Composite-key model configuration contract** (`$incrementing` line 9 and `$timestamps` line 11 in `tests/coverage/20260428_2310.txt`).
  - Cause: existing tests exercised relationships and delete behavior, but did not explicitly pin model-level configuration flags; boolean flip mutants survived.
  - Fix: added `test_model_uses_non_incrementing_and_no_timestamps` in `tests/Unit/Models/TripVisibilityTest.php` to assert both flags remain disabled.
  - Mutant IDs: `caaad87c256d7bcc`, `d94c9e952ed57e54`.

- **Mass-assignment contract for composite-key fields** (`$fillable` line 15 in `tests/coverage/20260428_2310.txt`).
  - Cause: the suite used `create()` with both fields but did not lock the exact fillable declaration; `RemoveArrayItem` mutants on `user_id` and `trip_id` persisted.
  - Fix: added `test_fillable_contains_user_id_and_trip_id` in `tests/Unit/Models/TripVisibilityTest.php` to assert the full fillable list.
  - Mutant IDs: `dc13c19680240135`, `9f208f3cc5b527e6`.

## CampaignMilestone (`app/Models/CampaignMilestone.php`)

- **Mass-assignment contract for milestone definition fields** (`$fillable` lines 13–17 in `tests/coverage/20260428_2310.txt`).
  - Cause: behavior tests covered relation and progress math, but did not explicitly lock the full fillable declaration; `RemoveArrayItem` mutants on milestone fields survived.
  - Fix: added `test_fillable_contains_expected_mass_assignable_attributes` in `tests/Unit/Models/CampaignMilestoneTest.php`.
  - Mutant IDs: `6fc7a582cc741cdc`, `9b713736720af9ed`, `e0c7f64beaf85f2e`, `032ca524eb978b0d`, `6216b922eb17e463`.

- **Progress percentage integer contract** (`getProgressPercentageAttribute()` line 45 in `tests/coverage/20260428_2310.txt`).
  - Cause: existing progress assertions covered exact integer-friendly ratios and cap behavior, but not a fractional ratio that requires integer truncation.
  - Fix: added `test_progress_percentage_returns_truncated_integer_value` asserting `100/333` resolves to `30` (not a float), while still using paid-donation totals.
  - Mutant IDs: `f6f4f1295d124329`, `e750ec2030ed6ea7`.

## SupportTicket (`app/Models/SupportTicket.php`)

- **Mass-assignment contract for support ticket lifecycle fields** (`$fillable` lines 13–25 in `tests/coverage/20260428_2310.txt`).
  - Cause: model tests validated relationships and datetime casts, but did not explicitly lock the full fillable declaration, so `RemoveArrayItem` mutants across ticket payload fields survived.
  - Fix: added `test_fillable_contains_expected_mass_assignable_attributes` in `tests/Unit/Models/SupportTicketTest.php` to assert the exact fillable list.
  - Mutant IDs: `1c5cc342ddd2b4cc`, `7542a5b58ca81c64`, `f457848fdffb4f47`, `cc4c97676e5970e3`, `1d33b4f1f133b2d6`, `d51c8be37ae42354`, `7b418d1b8c69f3f0`, `4cfc97a7905d4304`, `79d3e757f71a0780`, `1637ecc874f295ca`, `2d17d19743770972`, `9f67c557f0246b48`, `a2d5e9deb9e5d0e9`.
