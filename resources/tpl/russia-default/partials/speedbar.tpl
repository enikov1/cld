<span itemscope itemtype="https://schema.org/BreadcrumbList">
[loop speedbar.items]
<span itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem">
[item.is_current]
<span itemprop="name">{item.label}</span>
[/item.is_current]
[not-item.is_current]
<a href="{item.url|raw}" itemprop="item"><span itemprop="name">{item.label}</span></a>
[/not-item.is_current]
</span>[not-item.is_last] » [/not-item.is_last]
[/loop]
</span>
