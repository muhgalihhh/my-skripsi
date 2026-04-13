import json

with open("fastapi/scripts/notebook_tuning_webform_bertopic_lda.ipynb", "r", encoding="utf-8") as f:
    nb = json.load(f)

for cell in nb.get("cells", []):
    if cell.get("cell_type") == "code":
        source = "".join(cell.get("source", []))
        
        # We need to change the wordcloud logic.
        # Instead of `plot_wordcloud_grid` called twice, we replace `def plot_wordcloud_grid(...)` completely.
        
        if "def plot_wordcloud_grid(" in source:
            new_func = """def plot_combined_wordclouds(b_payloads, l_payloads, max_topics=8):
    if WordCloud is None:
        print("Wordcloud dilewati: package wordcloud belum tersedia.")
        return
    b_pl = [p for p in b_payloads[:max_topics] if p.get("freq")]
    l_pl = [p for p in l_payloads[:max_topics] if p.get("freq")]
    
    if not b_pl and not l_pl:
        print("Wordcloud dilewati: tidak ada topik valid.")
        return
        
    n_rows = max(len(b_pl), len(l_pl))
    fig, axes = plt.subplots(n_rows, 2, figsize=(12, 4 * n_rows))
    if n_rows == 1:
        axes = [axes]
    
    for i in range(n_rows):
        # BERTopic (Left)
        ax_b = axes[i][0]
        if i < len(b_pl):
            wc_b = WordCloud(width=800, height=400, background_color="white", colormap="viridis", max_words=100).generate_from_frequencies(b_pl[i]["freq"])
            ax_b.imshow(wc_b, interpolation="bilinear")
            ax_b.set_title(f"[BERTopic] {b_pl[i].get('title', '')}", fontsize=12)
        ax_b.axis("off")
        
        # LDA (Right)
        ax_l = axes[i][1]
        if i < len(l_pl):
            wc_l = WordCloud(width=800, height=400, background_color="white", colormap="plasma", max_words=100).generate_from_frequencies(l_pl[i]["freq"])
            ax_l.imshow(wc_l, interpolation="bilinear")
            ax_l.set_title(f"[LDA] {l_pl[i].get('title', '')}", fontsize=12)
        ax_l.axis("off")
        
    fig.suptitle("Komparasi WordCloud Topik Dominan (BERTopic vs LDA)", fontsize=16)
    plt.tight_layout()
    plt.show()

# Replace the function definition
"""
            
            import re
            
            # Since the cell starts with the func def, we can just replace until "print(\"=== Visualisasi BERTopic ===\")"
            # It's a bit rigid, so we'll use regex or str split.
            parts = source.split('print("=== Visualisasi BERTopic ===")')
            
            # And we need to remove the two calls to old `plot_wordcloud_grid` and instead just call the combined one.
            # Currently:
            # BERTopic prepares `wc_payloads`, calls plot
            # LDA prepares `wc_payloads`, calls plot
            
            pass

