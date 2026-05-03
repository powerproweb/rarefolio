use aiken/collection/list
use cardano/script_context.{ScriptContext}
use cardano/transaction.{InlineDatum, OutputReference, Transaction}
use sov_reserve/invariants.{
  same_non_economic_fields, valid_bucket_non_negative, valid_release_counters,
  valid_static_constants,
}
use sov_reserve/types.{
  Archive, Bucket, BuilderDao, Governance, QuarterRollover, ReleaseReserve,
  ReserveStateDatum, RotateGovernance, Strategic, StateRedeemer, Treasury,
  max_release_per_quarter,
}

validator reserve_state_validator {
  spend(
    datum_opt: Option<ReserveStateDatum>,
    redeemer: StateRedeemer,
    own_ref: OutputReference,
    self: Transaction,
  ) {
    expect Some(datum) = datum_opt
    expect Some(own_input) = self.inputs |> transaction.find_input(own_ref)

    let own_address = own_input.output.address
    let state_outputs = list.filter(self.outputs, fn(output) { output.address == own_address })
    expect [state_output] = state_outputs
    expect InlineDatum(next_raw) = state_output.datum
    expect next_from_output: ReserveStateDatum = next_raw

    valid_static_constants(datum)
      && valid_bucket_non_negative(datum)
      && valid_release_counters(datum)
      && has_threshold_signatures(self.extra_signatories, datum.governance)
      && when redeemer is {
        ReleaseReserve { bucket, amount, purpose: _, quarter_index, next_datum } ->
          release_transition_is_valid(datum, next_datum, bucket, amount, quarter_index)
            && next_from_output == next_datum

        QuarterRollover { new_quarter_index, next_datum } ->
          rollover_transition_is_valid(datum, next_datum, new_quarter_index)
            && next_from_output == next_datum

        RotateGovernance { new_governance, next_datum } ->
          governance_rotation_is_valid(datum, next_datum, new_governance)
            && next_from_output == next_datum
      }
  }

  else(_ctx: ScriptContext) {
    False
  }
}

fn release_transition_is_valid(
  current: ReserveStateDatum,
  next: ReserveStateDatum,
  bucket: Bucket,
  amount: Int,
  quarter_index: Int,
) -> Bool {
  amount > 0
    && bucket != Archive
    && quarter_index == current.quarter_index
    && current.archive_locked
    && same_non_economic_fields(current, next)
    && next.governance == current.governance
    && next.quarter_index == current.quarter_index
    && next.released_this_quarter == current.released_this_quarter + amount
    && next.released_total == current.released_total + amount
    && next.released_this_quarter <= max_release_per_quarter
    && bucket_delta_ok(current, next, bucket, amount)
    && valid_static_constants(next)
    && valid_bucket_non_negative(next)
    && valid_release_counters(next)
}

fn rollover_transition_is_valid(
  current: ReserveStateDatum,
  next: ReserveStateDatum,
  new_quarter_index: Int,
) -> Bool {
  current.archive_locked
    && new_quarter_index > current.quarter_index
    && same_non_economic_fields(current, next)
    && next.governance == current.governance
    && next.quarter_index == new_quarter_index
    && next.released_this_quarter == 0
    && next.released_total == current.released_total
    && buckets_unchanged(current, next)
    && valid_static_constants(next)
    && valid_bucket_non_negative(next)
    && valid_release_counters(next)
}

fn governance_rotation_is_valid(
  current: ReserveStateDatum,
  next: ReserveStateDatum,
  new_governance: Governance,
) -> Bool {
  current.archive_locked
    && governance_is_well_formed(new_governance)
    && same_non_economic_fields(current, next)
    && next.governance == new_governance
    && next.quarter_index == current.quarter_index
    && next.released_this_quarter == current.released_this_quarter
    && next.released_total == current.released_total
    && buckets_unchanged(current, next)
    && valid_static_constants(next)
    && valid_bucket_non_negative(next)
    && valid_release_counters(next)
}

fn bucket_delta_ok(
  current: ReserveStateDatum,
  next: ReserveStateDatum,
  bucket: Bucket,
  amount: Int,
) -> Bool {
  when bucket is {
    Treasury ->
      next.treasury_remaining == current.treasury_remaining - amount
        && next.builder_remaining == current.builder_remaining
        && next.strategic_remaining == current.strategic_remaining
        && next.archive_remaining == current.archive_remaining

    BuilderDao ->
      next.treasury_remaining == current.treasury_remaining
        && next.builder_remaining == current.builder_remaining - amount
        && next.strategic_remaining == current.strategic_remaining
        && next.archive_remaining == current.archive_remaining

    Strategic ->
      next.treasury_remaining == current.treasury_remaining
        && next.builder_remaining == current.builder_remaining
        && next.strategic_remaining == current.strategic_remaining - amount
        && next.archive_remaining == current.archive_remaining

    Archive -> False
  }
}

fn buckets_unchanged(a: ReserveStateDatum, b: ReserveStateDatum) -> Bool {
  a.treasury_remaining == b.treasury_remaining
    && a.builder_remaining == b.builder_remaining
    && a.strategic_remaining == b.strategic_remaining
    && a.archive_remaining == b.archive_remaining
}

fn governance_is_well_formed(governance: Governance) -> Bool {
  governance.threshold >= 1 && governance.threshold <= list.length(governance.signers)
}

fn has_threshold_signatures(provided, governance: Governance) -> Bool {
  count_present(governance.signers, provided) >= governance.threshold
}

fn count_present(required, provided) -> Int {
  when required is {
    [] -> 0
    [head, ..tail] ->
      if list.has(provided, head) {
        1 + count_present(tail, provided)
      } else {
        count_present(tail, provided)
      }
  }
}

fn test_governance() -> Governance {
  Governance {
    signers: [
      #"00000000000000000000000000000000000000000000000000000001",
      #"00000000000000000000000000000000000000000000000000000002",
      #"00000000000000000000000000000000000000000000000000000003",
    ],
    threshold: 2,
  }
}

fn test_datum(
  treasury_remaining: Int,
  builder_remaining: Int,
  strategic_remaining: Int,
  archive_remaining: Int,
  quarter_index: Int,
  released_this_quarter: Int,
  released_total: Int,
  governance: Governance,
) -> ReserveStateDatum {
  ReserveStateDatum {
    bar_id: "BAR-01",
    total_supply: 40_000,
    public_supply: 20_000,
    reserve_supply: 20_000,
    treasury_remaining,
    builder_remaining,
    strategic_remaining,
    archive_remaining,
    quarter_index,
    released_this_quarter,
    released_total,
    governance,
    archive_locked: True,
    policy_non_redeemable: True,
    policy_no_silver_claims: True,
  }
}

test release_transition_valid_for_treasury() {
  let gov = test_governance()
  let current = test_datum(10_000, 5_000, 3_000, 2_000, 3, 100, 1_000, gov)
  let next = test_datum(9_900, 5_000, 3_000, 2_000, 3, 200, 1_100, gov)
  release_transition_is_valid(current, next, Treasury, 100, 3)
}

test release_transition_rejects_cap_exceed() {
  let gov = test_governance()
  let current = test_datum(10_000, 5_000, 3_000, 2_000, 3, 950, 1_000, gov)
  let next = test_datum(9_900, 5_000, 3_000, 2_000, 3, 1_050, 1_100, gov)
  release_transition_is_valid(current, next, Treasury, 100, 3) == False
}

test release_transition_rejects_archive_bucket() {
  let gov = test_governance()
  let current = test_datum(10_000, 5_000, 3_000, 2_000, 3, 100, 1_000, gov)
  let next = test_datum(10_000, 5_000, 3_000, 1_900, 3, 200, 1_100, gov)
  release_transition_is_valid(current, next, Archive, 100, 3) == False
}

test rollover_transition_valid() {
  let gov = test_governance()
  let current = test_datum(10_000, 5_000, 3_000, 2_000, 3, 400, 2_000, gov)
  let next = test_datum(10_000, 5_000, 3_000, 2_000, 4, 0, 2_000, gov)
  rollover_transition_is_valid(current, next, 4)
}

test rollover_transition_rejects_non_reset_counter() {
  let gov = test_governance()
  let current = test_datum(10_000, 5_000, 3_000, 2_000, 3, 400, 2_000, gov)
  let next = test_datum(10_000, 5_000, 3_000, 2_000, 4, 1, 2_000, gov)
  rollover_transition_is_valid(current, next, 4) == False
}

test governance_rotation_valid() {
  let current_gov = test_governance()
  let new_gov =
    Governance {
      signers: [#"00000000000000000000000000000000000000000000000000000009"],
      threshold: 1,
    }

  let current = test_datum(10_000, 5_000, 3_000, 2_000, 3, 250, 1_250, current_gov)
  let next = test_datum(10_000, 5_000, 3_000, 2_000, 3, 250, 1_250, new_gov)
  governance_rotation_is_valid(current, next, new_gov)
}

test governance_rotation_rejects_invalid_threshold() {
  let current_gov = test_governance()
  let new_gov =
    Governance {
      signers: [#"00000000000000000000000000000000000000000000000000000009"],
      threshold: 2,
    }

  let current = test_datum(10_000, 5_000, 3_000, 2_000, 3, 250, 1_250, current_gov)
  let next = test_datum(10_000, 5_000, 3_000, 2_000, 3, 250, 1_250, new_gov)
  governance_rotation_is_valid(current, next, new_gov) == False
}

