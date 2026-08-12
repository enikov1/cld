<article class="review-item[item.is_editorial] review-item--editorial[/item.is_editorial]" data-review-id="{item.id}" itemscope itemtype="https://schema.org/Review">
    <div class="review-item__inner">
        <div class="comment-avatar" aria-hidden="true" style="--avatar-hue: {item.avatar_hue}">{item.avatar_initial}</div>
        <div class="review-content">
            <header class="review-head">
                <div class="review-head__meta">
                    <strong class="review-author" itemprop="author" itemscope itemtype="https://schema.org/{item.author_type}"><span itemprop="name">{item.author}</span></strong>
                    [item.is_editorial]
                        <span class="review-editorial-badge">{reviews_ui_editorial}</span>
                    [/item.is_editorial]
                    <time class="review-date" itemprop="datePublished" datetime="{item.created_at_iso}">{item.created_at}</time>
                </div>
                <div class="review-rating" title="{item.rating_label}" itemprop="reviewRating" itemscope itemtype="https://schema.org/Rating">
                    <meta itemprop="ratingValue" content="{item.rating}">
                    <meta itemprop="bestRating" content="10">
                    <meta itemprop="worstRating" content="1">
                    {item.stars_html|raw}
                    <span class="review-rating__value">{item.rating_label}</span>
                </div>
            </header>
            <div class="review-body comment-body" itemprop="reviewBody">{item.body_html|raw}</div>
        </div>
    </div>
</article>
