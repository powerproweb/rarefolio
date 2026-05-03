use aiken/collection/dict
use aiken/collection/list
use aiken/crypto.{VerificationKeyHash}
use cardano/assets.{PolicyId}
use cardano/script_context.{ScriptContext}
use cardano/transaction.{OutputReference, Transaction}
use sov_reserve/types.{
  BurnReserve, Governance, InitBar, MintRedeemer, archive_allocation, builder_allocation, public_supply,
  reserve_supply, state_token_name, strategic_allocation, total_supply,
  treasury_allocation,
}

validator bar_mint_policy(bootstrap_ref: OutputReference) {
  mint(redeemer: MintRedeemer, policy_id: PolicyId, self: Transaction) {
    when redeemer is {
      InitBar {
        bar_id,
        treasury_alloc,
        builder_alloc,
        strategic_alloc,
        archive_alloc,
        governance,
      } -> {
        let under_policy =
          self.mint
            |> assets.tokens(policy_id)
            |> dict.to_pairs()

        let total_minted = sum_quantities(under_policy)
        let state_token_qty = quantity_for_name(under_policy, state_token_name)
        let expected_total = total_supply + 1

        bar_id != #""
          && list.any(self.inputs, fn(input) { input.output_reference == bootstrap_ref })
          && governance_is_well_formed(governance)
          && has_threshold_signatures(self.extra_signatories, governance.signers, governance.threshold)
          && treasury_alloc == treasury_allocation
          && builder_alloc == builder_allocation
          && strategic_alloc == strategic_allocation
          && archive_alloc == archive_allocation
          && treasury_alloc + builder_alloc + strategic_alloc + archive_alloc == reserve_supply
          && public_supply + reserve_supply == total_supply
          && all_positive(under_policy)
          && total_minted == expected_total
          && state_token_qty == 1
      }

      BurnReserve { amount, reason_code, governance } -> {
        let under_policy =
          self.mint
            |> assets.tokens(policy_id)
            |> dict.to_pairs()

        amount > 0
          && reason_code != #""
          && governance_is_well_formed(governance)
          && has_threshold_signatures(self.extra_signatories, governance.signers, governance.threshold)
          && all_non_positive(under_policy)
          && sum_quantities(under_policy) <= -amount
          && quantity_for_name(under_policy, state_token_name) == 0
      }
    }
  }

  else(_ctx: ScriptContext) {
    False
  }
}

fn governance_is_well_formed(governance: Governance) -> Bool {
  governance.threshold >= 1 && governance.threshold <= list.length(governance.signers)
}

fn has_threshold_signatures(
  provided: List<VerificationKeyHash>,
  required: List<VerificationKeyHash>,
  threshold: Int,
) -> Bool {
  count_present(required, provided) >= threshold
}
fn count_present(
  required: List<VerificationKeyHash>,
  provided: List<VerificationKeyHash>,
) -> Int {
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

fn all_positive(tokens) -> Bool {
  when tokens is {
    [] -> True
    [Pair(_, quantity), ..rest] -> quantity > 0 && all_positive(rest)
  }
}

fn all_non_positive(tokens) -> Bool {
  when tokens is {
    [] -> True
    [Pair(_, quantity), ..rest] -> quantity <= 0 && all_non_positive(rest)
  }
}

fn sum_quantities(tokens) -> Int {
  when tokens is {
    [] -> 0
    [Pair(_, quantity), ..rest] -> quantity + sum_quantities(rest)
  }
}

fn quantity_for_name(tokens, asset_name: ByteArray) -> Int {
  when tokens is {
    [] -> 0
    [Pair(name, quantity), ..rest] ->
      if name == asset_name {
        quantity
      } else {
        quantity_for_name(rest, asset_name)
      }
  }
}

fn test_governance_2_of_3() -> Governance {
  Governance {
    signers: [
      #"00000000000000000000000000000000000000000000000000000001",
      #"00000000000000000000000000000000000000000000000000000002",
      #"00000000000000000000000000000000000000000000000000000003",
    ],
    threshold: 2,
  }
}

test governance_is_well_formed_ok() {
  governance_is_well_formed(test_governance_2_of_3())
}

test governance_is_well_formed_rejects_bad_threshold() {
  governance_is_well_formed(
    Governance {
      signers: [#"00000000000000000000000000000000000000000000000000000001"],
      threshold: 2,
    },
  ) == False
}

test has_threshold_signatures_ok() {
  let governance = test_governance_2_of_3()
  has_threshold_signatures(
    [
      #"00000000000000000000000000000000000000000000000000000001",
      #"00000000000000000000000000000000000000000000000000000003",
    ],
    governance.signers,
    governance.threshold,
  )
}

test has_threshold_signatures_rejects_too_few() {
  let governance = test_governance_2_of_3()
  has_threshold_signatures(
    [#"00000000000000000000000000000000000000000000000000000001"],
    governance.signers,
    governance.threshold,
  ) == False
}

test quantity_helpers_work_for_policy_tokens() {
  let pid: PolicyId = #"aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa"
  let tokens =
    assets.from_asset(pid, "X", 10)
      |> assets.add(pid, state_token_name, 1)
      |> assets.tokens(pid)
      |> dict.to_pairs()

  sum_quantities(tokens) == 11 && quantity_for_name(tokens, state_token_name) == 1
}

test sign_checks_for_positive_and_non_positive() {
  let pid: PolicyId = #"bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb"
  let positive_tokens =
    assets.from_asset(pid, "ONE", 1)
      |> assets.add(pid, "TWO", 2)
      |> assets.tokens(pid)
      |> dict.to_pairs()

  let non_positive_tokens =
    assets.from_asset(pid, "ONE", -1)
      |> assets.add(pid, "TWO", -2)
      |> assets.tokens(pid)
      |> dict.to_pairs()

  all_positive(positive_tokens)
    && all_non_positive(non_positive_tokens)
    && all_positive(non_positive_tokens) == False
}

