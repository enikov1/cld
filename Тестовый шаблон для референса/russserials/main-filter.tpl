<div class="filter-box">
	
		<label class="filt-label">
			<input type="checkbox">
			<span>Чекбокс <span>(10)</span></span>
		</label>
		
		<div class="filt-item">
			<div class="filt-cont">
				<input type="text" name="r-film_year" id="year" />
			</div>
		</div>

		<div class="filt-item">
			<div class="filt-capt">Селект</div>
			<div class="filt-cont filt-sel">
				<select name="sel" data-placeholder="Выбрать">
					<option></option>
					<option>Пункт 1</option>
					<option>Пункт 2</option>
					<option>Пункт 3</option>
				</select>
			</div>
		</div>
		
		<div class="filt-item">
			<div class="filt-capt">Мультиселект</div>
			<div class="filt-cont filt-sel">
				<select name="sel-multi" multiple data-placeholder="Выбрать">
					<option></option>
					<option>Пункт 1</option>
					<option>Пункт 2</option>
					<option>Пункт 3</option>
				</select>
			</div>
		</div>
		
		<div class="filt-submit fx-row">
			<input type="button" data-fieldsearch="submit" value="Поиск">
			<input type="button" data-fieldsearch="reset" value="Очистить">
		</div>
		
</div>