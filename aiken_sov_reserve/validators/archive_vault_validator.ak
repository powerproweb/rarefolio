use cardano/script_context.{ScriptContext}
use cardano/transaction.{OutputReference, Transaction}
use sov_reserve/types.{Governance, ReserveStateDatum}

validator archive_vault_validator {
  spend(
    datum_opt: Option<ReserveStateDatum>,
    _redeemer: Data,
    _own_ref: OutputReference,
    _self: Transaction,
  ) {
    expect Some(datum) = datum_opt
    datum.archive_locked == False
      && datum.policy_non_redeemable
      && datum.policy_no_silver_claims
      && False
  }

  else(_ctx: ScriptContext) {
    False
  }
}

fn test_datum() -> ReserveStateDatum {
  ReserveStateDatum {
    bar_id: "BAR-01",
    total_supply: 40_000,
    public_supply: 20_000,
    reserve_supply: 20_000,
    treasury_remaining: 10_000,
    builder_remaining: 5_000,
    strategic_remaining: 3_000,
    archive_remaining: 2_000,
    quarter_index: 1,
    released_this_quarter: 0,
    released_total: 0,
    governance:
      Governance {
        signers: [#"00000000000000000000000000000000000000000000000000000001"],
        threshold: 1,
      },
    archive_locked: True,
    policy_non_redeemable: True,
    policy_no_silver_claims: True,
  }
}

test archive_spend_rejects_even_with_valid_datum() {
  let own_ref =
    OutputReference {
      transaction_id: transaction.placeholder.id,
      output_index: 0,
    }

  archive_vault_validator.spend(
    Some(test_datum()),
    Void,
    own_ref,
    transaction.placeholder,
  ) == False
}

test archive_spend_requires_datum() fail {
  let own_ref =
    OutputReference {
      transaction_id: transaction.placeholder.id,
      output_index: 0,
    }

  archive_vault_validator.spend(
    None,
    Void,
    own_ref,
    transaction.placeholder,
  )
}

