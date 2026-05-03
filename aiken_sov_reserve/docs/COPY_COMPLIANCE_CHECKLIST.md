# Sovereign Treasury Reserve Copy-Compliance Checklist
This checklist is a hard release gate.
If any hard-fail item fails, release is blocked until fixed.

## Mandatory legal-position statements (must exist)
- NFTs do not grant ownership, redemption, lien, claim, beneficial interest, or entitlement to any silver.
- All silver remains solely owned by the issuer/operator at all times.
- NFTs are non-redeemable digital collectibles and/or platform access artifacts only.
- No promised appreciation, profit, yield, passive income, or guaranteed financial outcome.

## Required placements (must all pass)
- Primary mint page and any checkout or claim flow.
- Terms page and legal footer references.
- FAQ and collection overview pages.
- Whitepaper / litepaper / documentation pages.
- API docs and partner docs where token rights are described.
- Influencer / ambassador / affiliate media kit and script templates.
- Investor or strategic partner deck language where tokens are mentioned.
- Social template library (scheduled posts, announcement copy, ad drafts).

## Forbidden phrases (hard-fail on detection)
- "silver-backed NFT"
- "backed by silver" (unless immediately and explicitly negated as non-right-bearing)
- "redeemable for silver"
- "fractional ownership of silver"
- "silver ownership rights"
- "asset-backed claim"
- "guaranteed value"
- "guaranteed appreciation"
- "profit sharing"
- "passive income"
- "increase value" / "will increase in value" / "expected to rise"

## Allowed safe-language examples
- "Silver reference is part of project context and provenance narrative only."
- "NFTs confer no rights to metals or custody claims."
- "Collector value is market-driven and not guaranteed."
- "Reserve operations govern NFT supply only, not metal entitlement."

## Technical-to-copy consistency checks
- Contract docs explicitly state no `RedeemSilver` path exists.
- State invariants include non-redeemable and no-silver-claims flags.
- Public docs match contract constraints and do not imply hidden rights.
- Any metadata schemas and API fields avoid entitlement semantics.

## Partner and comms controls
- All external contributors must use pre-approved copy snippets.
- Any deviations require legal/compliance review before publication.
- Content review owner must sign off before merge/deploy.

## CI/release gate procedure
- Run phrase scanner across site pages, docs, templates, and campaign assets.
- Fail build on forbidden terms unless an explicit approved exception is attached.
- Run manual legal copy review on all newly changed public-facing content.
- Archive review log (reviewer, timestamp, files checked, result).

## Final release attestation (must be recorded)
- "No content in this release grants or implies NFT rights to silver."
- "All silver ownership remains solely with issuer/operator."
- "Release approved for publication under non-redeemable policy."
