[loop speedbar.items]
    [item.is_current]
        <span class="speedbar__current">{item.label}</span>
    [/item.is_current]
    [not-item.is_current]
        <a href="{item.url|raw}">{item.label}</a>
    [/not-item.is_current]
    [not-item.is_last]
        <span class="speedbar__sep"> » </span>
    [/not-item.is_last]
[/loop]
