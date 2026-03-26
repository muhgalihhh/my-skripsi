import json

data = json.load(open('data/skripsi_unsoed_scraped.json','r',encoding='utf-8'))

# Cek distribusi panjang kesimpulan
conc_lens = [(i, len(d.get('Kesimpulan',''))) for i, d in enumerate(data) if d.get('Kesimpulan','')]
short_conc = [(i, l) for i, l in conc_lens if l < 100]
print(f'Kesimpulan < 100 chars: {len(short_conc)}')
very_short = [(i, l) for i, l in conc_lens if l < 50]
print(f'Kesimpulan < 50 chars: {len(very_short)}')

# Sample beberapa kesimpulan pendek
for i, l in short_conc[:5]:
    print(f'\n--- Data {i} (len={l}) ---')
    print(f"Kesimpulan: {data[i]['Kesimpulan'][:200]}")

# Sample beberapa kesimpulan normal
print('\n\n=== SAMPLE KESIMPULAN NORMAL ===')
normal_conc = [(i, l) for i, l in conc_lens if 500 < l < 2000]
for i, l in normal_conc[:3]:
    print(f'\n--- Data {i} (len={l}) ---')
    print(f"Kesimpulan: {data[i]['Kesimpulan'][:300]}...")

# Sample tanpa kesimpulan
print('\n\n=== SAMPLE TANPA KESIMPULAN (ABSTRAK SAJA) ===')
no_conc = [i for i, d in enumerate(data) if not d.get('Kesimpulan','')]
for i in no_conc[:3]:
    print(f'\n--- Data {i} ---')
    abs_text = data[i].get('Abstrak', '')
    print(f"Abstrak ({len(abs_text)} chars): {abs_text[:200]}...")
    print(f"Sumber Kesimpulan: {data[i].get('Sumber Kesimpulan', '')}")

# Cek berapa yang sumber kesimpulannya dari PDF
print('\n\n=== SUMBER KESIMPULAN ===')
sources = {}
for d in data:
    src = d.get('Sumber Kesimpulan', '') or 'Tidak ada'
    sources[src] = sources.get(src, 0) + 1
for src, cnt in sorted(sources.items(), key=lambda x: -x[1]):
    print(f"  {src}: {cnt}")

# Cek variasi panjang combined text antara yang punya dan tidak punya kesimpulan
print('\n\n=== PERBANDINGAN PANJANG TEKS ===')
with_conc = [d for d in data if d.get('Kesimpulan','')]
without_conc = [d for d in data if not d.get('Kesimpulan','')]

avg_with = sum(len(d.get('Abstrak','')) + len(d.get('Kesimpulan','')) for d in with_conc) / len(with_conc)
avg_without = sum(len(d.get('Abstrak','')) for d in without_conc) / len(without_conc)

print(f"Avg combined length (with conclusion): {avg_with:.0f} chars")
print(f"Avg combined length (without conclusion, abstract only): {avg_without:.0f} chars")
print(f"Ratio: {avg_with/avg_without:.2f}x")
