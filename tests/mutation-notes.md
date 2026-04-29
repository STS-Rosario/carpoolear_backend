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
