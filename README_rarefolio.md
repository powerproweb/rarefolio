


# rarefolio
Our Mission: To deliver a provenance-first collector experience where: The art is Premium and Usable (downloadable, display-worthy, print-ready), the ownership is clear and verifiable (clean IDs, consistent indexing, batch logic), and the environment is high-performance and respectful of the user’s hardware (no “melt your GPU” nonsense).

Based on everything we've built and what's still open, here's where you stand:

What's Done
•  Homepage, collections hub, calculator, contact, terms, privacy, manifesto, bio, philosophy, downloads, 404, prelaunch, thank-you pages
•  Silver Bar I collection page with batch-navigated grid (batches 1–15 live, 16–5000 ready via DB)
•  NFT detail page (URL-param driven)
•  Certificate pipeline: issuance API, art-directed PDFs (parchment/cream + 20 wax seals), verification, download
•  DB-driven block routing system (scales to 5,000 batches per bar, multi-bar)
•  Story placeholder files for all 15 blocks + per-item stubs for Inventors, Aries, Robot Butler
•  AGENTS.md, CHANGELOG, .htaccess all current

Still To Do

DB Setup (deploy blockers)
1. Run BLOCKS_DB_SCHEMA.sql in phpMyAdmin (creates qd_blocks + qd_stories)
2. Hit seed_blocks.php to migrate the first 15 blocks + stories into DB
3. Upload all new files to BlueHost

Content (your authoring work)
4. Write the 12 story placeholders with real lore (blocks 03–14: Robot Butler, Gemini → Pisces, New Series)
5. Write per-item stories for block01 Inventors (8 files: 1.html–8.html)
6. Write per-item stories for block02 Aries (replace stubs)
7. Write per-item stories for block03 Robot Butler (replace stubs)
8. Register blocks 16–5,000 for Bar I via manage_blocks.php (can be scripted with curl)
9. Author stories for blocks 16+ via manage_stories.php

Frontend gaps
10. Uncomment and finish hero sections on index.html and collections.html
11. Create standalone collection sub-pages for the remaining 11 blocks (Gemini through New Series — currently only Taurus, Aries, Inventors have dedicated pages)
12. Build out Silver Bar II and III collection pages (currently placeholders)
13. Create about.html (commented out in nav) or remove the nav comment
14. Add site.webmanifest for PWA support (commented out in all pages) — optional

Polish
15. Test the full cert issuance flow end-to-end on production (issue a cert, verify, download PDF)
16. Test block API resolution for batches 16+ after seeding some test blocks
17. Consider templating/includes for the duplicated nav/header/footer across 22+ HTML files — optional but reduces maintenance

Items 1–3 are the immediate deploy steps. Items 4–9 are the content pipeline that only you can do. Items 10–14 are frontend work I can help with whenever you're ready.