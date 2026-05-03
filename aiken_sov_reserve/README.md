# Sovereign Treasury Reserve (Aiken)
This package implements Rarefolio’s non-redeemable sovereign reserve rules as Cardano validators.
## Core policy guarantees
- NFTs are non-redeemable and do not grant rights to silver.
- Silver ownership remains solely with issuer/operator.
- Reserve releases are supply-governance actions only, not metal entitlement actions.
## Contract layout
- `validators/bar_mint_policy.ak`
  - One-shot mint path (`InitBar`) gated by a bootstrap UTxO.
  - Enforces fixed allocation constants and governance threshold signatures.
  - Requires exactly one state token (`state_token_name`) in initialization mint.
  - Burn path (`BurnReserve`) allows authorized burns only, rejects state-token burns.
- `validators/reserve_state_validator.ak`
  - Enforces state transitions for:
    - `ReleaseReserve` (bucket debit + quarterly cap)
    - `QuarterRollover` (counter reset only on quarter advance)
    - `RotateGovernance` (well-formed signer threshold only)
  - Requires datum continuity and invariant preservation across transitions.
  - Blocks archive bucket release through transition checks.
- `validators/archive_vault_validator.ak`
  - v1 hard-lock behavior: spend always rejects.
## Domain model
- Types/constants: `lib/sov_reserve/types.ak`
- Invariants: `lib/sov_reserve/invariants.ak`
  - Static supply and policy flags
  - Non-negative balances and counters
  - Initial allocation consistency
  - Non-economic field continuity checks
## Policy constants (v1)
- `total_supply = 40_000`
- `public_supply = 20_000`
- `reserve_supply = 20_000`
- Reserve buckets:
  - `treasury_allocation = 10_000`
  - `builder_allocation = 5_000`
  - `strategic_allocation = 3_000`
  - `archive_allocation = 2_000`
- `max_release_per_quarter = 1_000`
## Build & test
- Build:
  - `aiken build`
- Tests:
  - `aiken check`
## Current test coverage
- Governance validity + threshold signature helpers
- Mint-policy quantity/sign checks
- Reserve release transition acceptance/rejection cases
- Quarter rollover acceptance/rejection cases
- Governance rotation acceptance/rejection cases
- Archive validator always-reject behavior
- Invariant function checks
## Compliance copy
See `docs/COPY_COMPLIANCE_CHECKLIST.md` for mandatory public-language restrictions and release gating requirements.
