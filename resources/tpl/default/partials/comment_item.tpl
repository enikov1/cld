<article class="comment-item[item.is_reply] comment-item--reply[/item.is_reply][item.is_pinned] comment-item--pinned[/item.is_pinned]" data-comment-id="{item.id}">
    <div class="comment-item__inner">
        <div class="comment-avatar" aria-hidden="true" style="--avatar-hue: {item.avatar_hue}">{item.avatar_initial}</div>
        <div class="comment-content">
            <header class="comment-head">
                <div class="comment-head__meta">
                    <strong class="comment-author">{item.author}</strong>
                    [item.is_pinned]
                        <span class="comment-pinned-badge">{comments_ui_pinned}</span>
                    [/item.is_pinned]
                    <time class="comment-date">{item.created_at}</time>
                </div>
            </header>
            <div class="comment-body">{item.body_html|raw}</div>
            <footer class="comment-footer">
                [has_comments_vote]
                    <div class="comment-vote">
                        <button type="button"
                                class="comment-vote__btn comment-vote__btn--like dontusebuttonclass[item.user_vote_like] active-like[/item.user_vote_like]"
                                data-comment-vote="1"
                                title="{comments_ui_vote_like}">
                            <span class="fa fa-thumbs-up" aria-hidden="true"></span>
                            <span data-comment-likes>{item.likes}</span>
                        </button>
                        <button type="button"
                                class="comment-vote__btn comment-vote__btn--dislike dontusebuttonclass[item.user_vote_dislike] active-dislike[/item.user_vote_dislike]"
                                data-comment-vote="-1"
                                title="{comments_ui_vote_dislike}">
                            <span data-comment-dislikes>{item.dislikes}</span>
                            <span class="fa fa-thumbs-down" aria-hidden="true"></span>
                        </button>
                    </div>
                [/has_comments_vote]
                <button type="button" class="dontusebuttonclass comment-reply-btn">{comments_ui_reply}</button>
            </footer>
        </div>
    </div>
    [item.children_html]<div class="comment-replies">{item.children_html|raw}</div>[/item.children_html]
</article>
