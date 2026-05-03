use sov_reserve/types.{
  Governance, ReserveStateDatum, archive_allocation, builder_allocation,
  policy_no_silver_claims, policy_non_redeemable, public_supply, reserve_supply,
  strategic_allocation, total_supply, treasury_allocation,
}

pub fn valid_static_constants(d: ReserveStateDatum) -> Bool {
  d.total_supply == total_supply
    && d.public_supply == public_supply
    && d.reserve_supply == reserve_supply
    && d.policy_non_redeemable == policy_non_redeemable
    && d.policy_no_silver_claims == policy_no_silver_claims
}

pub fn valid_bucket_non_negative(d: ReserveStateDatum) -> Bool {
  d.treasury_remaining >= 0
    && d.builder_remaining >= 0
    && d.strategic_remaining >= 0
    && d.archive_remaining >= 0
}

pub fn valid_release_counters(d: ReserveStateDatum) -> Bool {
  d.released_this_quarter >= 0 && d.released_total >= 0
}

pub fn valid_initial_buckets(d: ReserveStateDatum) -> Bool {
  d.treasury_remaining == treasury_allocation
    && d.builder_remaining == builder_allocation
    && d.strategic_remaining == strategic_allocation
    && d.archive_remaining == archive_allocation
}

pub fn same_non_economic_fields(a: ReserveStateDatum, b: ReserveStateDatum) -> Bool {
  a.bar_id == b.bar_id
    && a.total_supply == b.total_supply
    && a.public_supply == b.public_supply
    && a.reserve_supply == b.reserve_supply
    && a.policy_non_redeemable == b.policy_non_redeemable
    && a.policy_no_silver_claims == b.policy_no_silver_claims
    && a.archive_locked == b.archive_locked
}

fn test_governance() {
  Governance {
    signers: [
      #"00000000000000000000000000000000000000000000000000000001",
      #"00000000000000000000000000000000000000000000000000000002",
      #"00000000000000000000000000000000000000000000000000000003",
    ],
    threshold: 2,
  }
}

fn test_datum() {
  ReserveStateDatum {
    bar_id: "BAR-01",
    total_supply,
    public_supply,
    reserve_supply,
    treasury_remaining: treasury_allocation,
    builder_remaining: builder_allocation,
    strategic_remaining: strategic_allocation,
    archive_remaining: archive_allocation,
    quarter_index: 1,
    released_this_quarter: 0,
    released_total: 0,
    governance: test_governance(),
    archive_locked: True,
    policy_non_redeemable,
    policy_no_silver_claims,
  }
}

test valid_static_constants_ok() {
  valid_static_constants(test_datum())
}

test valid_static_constants_rejects_wrong_supply() {
  let d = test_datum()
  valid_static_constants(ReserveStateDatum { ..d, total_supply: 39_999 }) == False
}

test valid_initial_buckets_ok() {
  valid_initial_buckets(test_datum())
}

