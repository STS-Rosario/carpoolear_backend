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

## TripSearchRepository

- Cluster `TripSearchRepository.php` `trackSearch` (stale report ~1050–1078 in `tests/coverage/20260428_2310.txt`): `total() ?? count()`, `$trips->count() > 0` + `seats_available <= 0` filter, `$searchData` keys / `create()` return.
  - Cause: only LengthAwarePaginator + single carpooleado was exercised; `total()` must exist on `$trips` (calling `$trips->total()` throws before `??` on a bare Collection — branch matters when `total()` returns `null`). Persisted columns were not asserted in one combined DB shape check for RemoveArrayItem clusters on payload keys.
  - Fix: added `test_track_search_falls_back_to_count_when_total_returns_null` (anonymous `$trips` stub with `total()` returning `null`), `test_track_search_counts_each_full_trip_as_carpooleado`, `test_track_search_persists_search_payload_columns_for_array_remove_mutants`.

## MessageRepository

- Cluster `MessageRepository.php` (`tests/coverage/20260428_2310.txt` ~1080–1108): `changeMessageReadState` / `createMessageReadState` pivot payloads, `getMessagesUnread` `$item->pivot->read == 0`, `markMessages` bulk `update` keys.
  - Cause: survivor mutants removed pivot `read` keys or dropped `updated_at` from the bulk update without assertions on raw `user_message_read` / `conversations_users` rows; loose `== 0` vs `=== 0` was not distinguished when `read` is stored as `'0'`.
  - Fix: added `test_mark_messages_updates_user_message_read_updated_at`, `test_change_message_read_state_persists_read_flag_in_user_message_read`, `test_create_message_read_state_inserts_read_into_user_message_read`, `test_get_messages_unread_includes_conversation_when_pivot_read_is_loosely_zero`.

## PassengersRepository

- Cluster `PassengersRepository.php` (`tests/coverage/20260428_2310.txt` ~1166–1259): `newRequest` create payload keys; `cancelRequest` `==` vs constants (`EqualToIdentical`); `getPendingRequests` / `getPendingPaymentRequests` trip joins + `trips.deleted_at`; `userHasActiveRequest` `whereIn` states.
  - Cause: create-array RemoveArrayItem mutants had only scalar asserts on the returned model; cancel-branch mutants compared ints strictly vs `'0'` / `'3'` request payloads from callers coerced to string; listing helpers lacked explicit exclusion proof when the joined trip is soft-deleted; `WAITING_PAYMENT` was not asserted separately inside `userHasActiveRequest` coverage.
  - Fix: strengthened `test_new_request_creates_pending_passenger_row` with `assertDatabaseHas`; added `test_cancel_request_matches_reason_via_loose_equality_for_string_literals`, `test_get_pending_requests_without_trip_id_excludes_soft_deleted_trips`, `test_get_pending_payment_requests_excludes_soft_deleted_trips`, `test_user_has_active_request_includes_waiting_payment_state`.

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

## FileRepository

- Cluster `FileRepository.php` (`tests/coverage/20260428_2310.txt` ~1350+): `createFromFile` directory creation flags and `createFromData` generated-name branch (`$name` null).
  - Cause: file-move happy path covered only a shallow folder; recursive `makeDirectory(..., 0777, true, true)` mutations can survive unless nested missing folders are asserted. Data-upload test pinned only named-file path, leaving the autogenerated filename branch (`microtime`/`date` + extension) under-asserted when `$name` is null.
  - Fix: added `test_create_from_file_creates_nested_folder_recursively` and `test_create_from_data_generates_filename_when_name_is_null` with existence and extension assertions for generated output.

## NotificationRepository

- `NotificationRepository.php` `getNotifications` signature/default and pagination gate (`tests/coverage/20260428_2310.txt` ~1439–1445): `FalseToTrue` on `$unread = false` and `BooleanAndToBooleanOr` on `$page_size && $page`.
  - Cause: tests always passed `$unread` explicitly and only exercised pagination when both values existed; mutating the default to true or the gate from AND to OR could still pass.
  - Fix: added `test_get_notifications_default_argument_uses_all_notifications_not_unread_only` and `test_get_notifications_does_not_paginate_when_only_page_size_or_page_is_provided`.

## UserRepository

- `UserRepository.php` `show` absent-user path (`~41–45`): `first()` may return null; `private_note` stripping must stay behind `if ($user)`.
  - Cause: tests always loaded an existing id, so skipping the reset body could mutate without failing.
  - Fix: added `test_show_returns_null_when_user_not_found`.

- `UserRepository.php` `addFriend` pivot payload (`~131–136`): both `attach` calls carry `origin` + `state`; mutations could drop columns or change `$provider` handling.
  - Cause: friendship existence was asserted via relations only; pivot `origin` was never checked against the `$provider` argument.
  - Fix: strengthened `test_add_friend_and_delete_friend_sync_bidirectional_pivot` with `assertDatabaseHas('friends', …)` for both directions.

- `UserRepository.php` password-reset lookups (`getUserByResetToken` ~162–167, `getLastPasswordReset` ~170–175): missing-token / unknown-email paths.
  - Cause: happy-path round-trip asserted resolution only; removing the `if ($pr)` guard or returning a dummy model could survive without explicit absence checks.
  - Fix: added `test_password_reset_token_lookups_return_null_when_missing`.

- `UserRepository.php` (`tests/coverage/20260428_2310.txt` ~1464–1474): `show()` eager-load list (`accounts`, `donations`, `referencesReceived`, `cars`) and `acceptTerms`/`updatePhoto` return values (`AlwaysReturnNull`).
  - Cause: `show` test only checked `private_note` nulling, so dropping relation keys from `with([...])` could survive. `acceptTerms`/`updatePhoto` effects were asserted via fresh model state, but method return contracts were not, allowing null-return mutations.
  - Fix: strengthened `test_show_nulls_private_note_and_loads_relations` with `relationLoaded` assertions for all four relations; strengthened `test_accept_terms_and_update_photo` with non-null return and identity (`$user->id`) checks for both methods.

## RoutesRepository

- `RoutesRepository.php` (`tests/coverage/20260428_2310.txt` ~1122–1152): `getPotentialsNodes` lng max/min branch (`if ($n1->lng > $n2->lng)`), `autocomplete` log concat (`$name.' '.$country`), and `whereRaw(CONCAT(name, state, country) like ?)` filtering.
  - Cause: bounding-box test primarily exercised one ordering shape; reversed-lng branch comparators were under-asserted. Autocomplete tests matched mostly by name and country, so state/country concat and exact log message construction could mutate without failure.
  - Fix: added `test_get_potentials_nodes_handles_reversed_lng_order_and_keeps_bounds` and `test_autocomplete_matches_state_country_concat_and_logs_query_context` (state-token search + `Log::shouldReceive('info')->with($needle.' AR')`).

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
