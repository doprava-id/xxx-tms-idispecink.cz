# xxx-tms-idispecink.cz

Repozitář pro TMS (Transport Management System) firmy **iDispečink.cz s.r.o.** —
české silniční nákladní dopravy a dispečinku.

> **Stav: prázdný scaffold.** Repozitář zatím neobsahuje žádný kód, build ani testy —
> jen licenci, tento README a `CLAUDE.md`. Popis architektury níže proto zatím
> neexistuje; doplní se s prvním reálným kódem.

## Kontext

Provoz dispečinku dnes běží nad Airtable (tabulka `Přepravy` jako zdroj pravdy),
Blue Yonder TMS (účty ESA a WELLPACK/Chep), denními Trello nástěnkami, exporty do
Excelu a objednávkami v PDF/Wordu.

**Stávající automatizace není v tomto repozitáři.** Import z Blue Yonderu, týdenní KT
reporty i generování objednávek přepravy běží v samostatné lokální pipeline a v Claude
skillech. Tento repozitář je zatím prázdný a nic z toho nenahrazuje.

## Pro AI asistenty

Pracovní pravidla, konvence větvení a commitů, zacházení s citlivými daty a hranice
toho, co v repozitáři skutečně je, najdeš v [`CLAUDE.md`](CLAUDE.md). Přečti si ho
dřív, než začneš cokoliv psát.

## Licence

Apache License 2.0 — viz [`LICENSE`](LICENSE).
