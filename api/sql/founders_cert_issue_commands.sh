#!/usr/bin/env bash
# =============================================================================
#  Rarefolio, Founders Block 88 Certificate Issuance Commands
# =============================================================================
#  Run AFTER Phase 2 (mainnet mint) is confirmed.
#
#  Before running:
#    1. Replace MAINNET_TX_HASH_NNN with the real mint_tx_hash for each token
#    2. Replace MAINNET_POLICY_ID with the actual Cardano policy ID
#    3. Replace FOUNDER_WALLET_ADDR with the founder's Cardano wallet address
#    4. Export ADMIN_USER and ADMIN_PASS in your shell (values live in
#       api/_config.php on the server / your password manager), for example:
#         export ADMIN_USER='<from api/_config.php ADMIN_USER>'
#         export ADMIN_PASS='<from api/_config.php ADMIN_PASS>'
#       This script will fail fast if either is unset.
#
#  Template/seal assignments are deterministic (cnft_num mod pool_size):
#    CNFT#   num    template    seal
#    0000705  705   parchment   gold  (bg: parchment_01, seal: gold_01)
#    0000706  706   parchment   gold  (bg: parchment_02, seal: gold_02)
#    0000707  707   parchment   gold  (bg: parchment_03, seal: gold_03)
#    0000708  708   parchment   gold  (bg: parchment_04, seal: gold_04)
#    0000709  709   parchment   gold  (bg: parchment_01, seal: gold_05)
#    0000710  710   parchment   gold  (bg: parchment_02, seal: gold_06)
#    0000711  711   parchment   gold  (bg: parchment_03, seal: gold_07)
#    0000712  712   parchment   gold  (bg: parchment_04, seal: gold_08)
#
#  All 8 use gold seals, the Founders collection uses full gold pool exhaustion
#  across the 8-piece set, one per seal variant.
# =============================================================================

BASE_URL="https://rarefolio.io"
: "${ADMIN_USER:?ADMIN_USER env var required (see api/_config.php ADMIN_USER)}"
: "${ADMIN_PASS:?ADMIN_PASS env var required (see api/_config.php ADMIN_PASS)}"
BAR_SERIAL="E101837"
COLLECTION="Founders Collection, Silver Bar I"
NETWORK="mainnet"
POLICY_ID="MAINNET_POLICY_ID"           # <-- replace after mint
FOUNDER_WALLET="FOUNDER_WALLET_ADDR"    # <-- replace with actual wallet

issue_cert() {
  local CNFT_ID="$1"
  local CNFT_NUM="$2"
  local EDITION="$3"
  local TX_HASH="$4"
  local TOKEN_ID="$5"

  curl -s -X POST "${BASE_URL}/api/admin/issue_cert.php" \
    -u "${ADMIN_USER}:${ADMIN_PASS}" \
    -H "Content-Type: application/json" \
    -d "{
      \"certId\":                  \"QDCERT-${BAR_SERIAL}-${CNFT_NUM}\",
      \"cnftId\":                  \"${CNFT_ID}\",
      \"barSerial\":               \"${BAR_SERIAL}\",
      \"collection\":              \"${COLLECTION}\",
      \"edition\":                 \"${EDITION}\",
      \"silverAllocationTroyOz\":  \"0.00025\",
      \"template\":                \"parchment\",
      \"sealColor\":               \"gold\",
      \"network\":                 \"${NETWORK}\",
      \"contractAddress\":         \"${POLICY_ID}\",
      \"tokenId\":                 \"${TOKEN_ID}\",
      \"txHash\":                  \"${TX_HASH}\",
      \"blockNumber\":             \"\",
      \"buyerName\":               \"The Founders\",
      \"privacyEnabled\":          true,
      \"wallet\":                  \"${FOUNDER_WALLET}\",
      \"vaultRecordId\":           \"VAULT-${BAR_SERIAL}-${CNFT_NUM}\"
    }" | python3 -m json.tool 2>/dev/null || echo "CERT ${CNFT_NUM}: response not JSON, check server"

  echo ""
}

echo "=== Founders Block 88 Certificate Issuance ==="
echo "Bar: ${BAR_SERIAL}  |  Network: ${NETWORK}"
echo ""

issue_cert "qd-silver-0000705" "0000705" "Edition 1 of 8, Founders" "MAINNET_TX_HASH_705" "qd-silver-0000705"
issue_cert "qd-silver-0000706" "0000706" "Edition 2 of 8, Founders" "MAINNET_TX_HASH_706" "qd-silver-0000706"
issue_cert "qd-silver-0000707" "0000707" "Edition 3 of 8, Founders" "MAINNET_TX_HASH_707" "qd-silver-0000707"
issue_cert "qd-silver-0000708" "0000708" "Edition 4 of 8, Founders" "MAINNET_TX_HASH_708" "qd-silver-0000708"
issue_cert "qd-silver-0000709" "0000709" "Edition 5 of 8, Founders" "MAINNET_TX_HASH_709" "qd-silver-0000709"
issue_cert "qd-silver-0000710" "0000710" "Edition 6 of 8, Founders" "MAINNET_TX_HASH_710" "qd-silver-0000710"
issue_cert "qd-silver-0000711" "0000711" "Edition 7 of 8, Founders" "MAINNET_TX_HASH_711" "qd-silver-0000711"
issue_cert "qd-silver-0000712" "0000712" "Edition 8 of 8, Founders" "MAINNET_TX_HASH_712" "qd-silver-0000712"

echo "=== Done. Verify certs at: ==="
echo "  ${BASE_URL}/cert?cert=QDCERT-${BAR_SERIAL}-0000705"
echo "  ${BASE_URL}/verify?cert=QDCERT-${BAR_SERIAL}-0000705"
