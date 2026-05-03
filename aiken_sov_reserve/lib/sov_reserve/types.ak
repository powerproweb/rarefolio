use aiken/crypto.{VerificationKeyHash}

pub const total_supply: Int = 40_000
pub const public_supply: Int = 20_000
pub const reserve_supply: Int = 20_000

pub const treasury_allocation: Int = 10_000
pub const builder_allocation: Int = 5_000
pub const strategic_allocation: Int = 3_000
pub const archive_allocation: Int = 2_000

pub const max_release_per_quarter: Int = 1_000
pub const state_token_name: ByteArray = "STATE"

pub const policy_non_redeemable: Bool = True
pub const policy_no_silver_claims: Bool = True

pub type Bucket {
  Treasury
  BuilderDao
  Strategic
  Archive
}

pub type ReleasePurpose {
  DevFunding
  LiquiditySupport
  StrategicPartnership
  DaoApprovedGrowth
}

pub type Governance {
  Governance {
    signers: List<VerificationKeyHash>,
    threshold: Int,
  }
}

pub type ReserveStateDatum {
  ReserveStateDatum {
    bar_id: ByteArray,
    total_supply: Int,
    public_supply: Int,
    reserve_supply: Int,
    treasury_remaining: Int,
    builder_remaining: Int,
    strategic_remaining: Int,
    archive_remaining: Int,
    quarter_index: Int,
    released_this_quarter: Int,
    released_total: Int,
    governance: Governance,
    archive_locked: Bool,
    policy_non_redeemable: Bool,
    policy_no_silver_claims: Bool,
  }
}

pub type MintRedeemer {
  InitBar {
    bar_id: ByteArray,
    treasury_alloc: Int,
    builder_alloc: Int,
    strategic_alloc: Int,
    archive_alloc: Int,
    governance: Governance,
  }
  BurnReserve {
    amount: Int,
    reason_code: ByteArray,
    governance: Governance,
  }
}

pub type StateRedeemer {
  ReleaseReserve {
    bucket: Bucket,
    amount: Int,
    purpose: ReleasePurpose,
    quarter_index: Int,
    next_datum: ReserveStateDatum,
  }
  QuarterRollover {
    new_quarter_index: Int,
    next_datum: ReserveStateDatum,
  }
  RotateGovernance {
    new_governance: Governance,
    next_datum: ReserveStateDatum,
  }
}

