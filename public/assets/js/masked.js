function maskedPhone(inputElement) {
    const mask = inputElement.getAttribute('placeholder') || '+375 (__) ___-__-__';
    const allowedChars = new Set(['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']);
    
    // Определяем неизменяемые позиции и символы на основе маски
    const immutablePositions = new Set();
    const immutableChars = {};
    const editablePositions = [];
    
    for (let i = 0; i < mask.length; i++) {
        if (mask[i] !== '_') {
            immutablePositions.add(i);
            immutableChars[i] = mask[i];
        } else {
            editablePositions.push(i);
        }
    }

    function handleInput(e) {
        let value = this.value;
        let newValue = '';
        let digitCount = 0;
        
        // Обрабатываем каждый символ
        for (let i = 0; i < value.length && i < mask.length; i++) {
            if (immutablePositions.has(i)) {
                // Вставляем неизменяемый символ из маски
                newValue += immutableChars[i];
            } else {
                // Обрабатываем изменяемую позицию
                const char = value[i];
                if (allowedChars.has(char)) {
                    newValue += char;
                    digitCount++;
                } else if (i < newValue.length) {
                    // Если символ не цифра, но позиция уже заполнена
                    newValue += newValue[i];
                } else {
                    // Заменяем на placeholder из маски
                    newValue += '_';
                }
            }
        }
        
        // Добавляем оставшуюся часть маски, если ввод не завершен
        if (newValue.length < mask.length) {
            for (let i = newValue.length; i < mask.length; i++) {
                newValue += immutablePositions.has(i) ? immutableChars[i] : '_';
            }
        }
        
        this.value = newValue;
        
        // Устанавливаем курсор на первую свободную позицию
        setCursorPosition(this);
    }

    function handleKeyDown(e) {
        const selectionStart = this.selectionStart;
        const selectionEnd = this.selectionEnd;
        const isAllSelected = selectionStart === 0 && selectionEnd === this.value.length;

        // Разрешаем навигационные клавиши и комбинации
        const allowedKeys = new Set([
            'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
            'Home', 'End', 'Tab', 'Escape', 'Enter',
            'Meta', 'Control', 'Alt', 'Shift',
            'F1', 'F2', 'F3', 'F4', 'F5', 'F6', 'F7', 'F8', 'F9', 'F10', 'F11', 'F12'
        ]);

        // Пропускаем разрешенные клавиши
        if (allowedKeys.has(e.key) || e.ctrlKey || e.metaKey || e.altKey) {
            return;
        }

        // Обработка очистки при полном выделении
        if (isAllSelected && (e.key === 'Backspace' || e.key === 'Delete')) {
            setTimeout(() => {
                let newValue = '';
                for (let i = 0; i < mask.length; i++) {
                    newValue += immutablePositions.has(i) ? immutableChars[i] : '_';
                }
                this.value = newValue;
                setCursorPosition(this, editablePositions[0] || 0);
            }, 0);
            return;
        }

        // Обработка Backspace
        if (e.key === 'Backspace') {
            setTimeout(() => {
                if (selectionStart === selectionEnd) {
                    let pos = selectionStart - 1;
                    while (pos >= 0 && immutablePositions.has(pos)) pos--;
                    
                    if (pos >= 0 && !immutablePositions.has(pos)) {
                        const newValue = this.value.substring(0, pos) + '_' + this.value.substring(pos + 1);
                        this.value = newValue;
                        setCursorPosition(this, pos);
                    }
                } else {
                    let hasImmutable = false;
                    for (let i = selectionStart; i < selectionEnd; i++) {
                        if (immutablePositions.has(i)) {
                            hasImmutable = true;
                            break;
                        }
                    }
                    
                    if (!hasImmutable) {
                        setTimeout(() => {
                            handleInput.call(this, { target: this });
                        }, 0);
                    }
                }
            }, 0);
            return;
        }

        // Обработка Delete
        if (e.key === 'Delete') {
            setTimeout(() => {
                if (selectionStart === selectionEnd) {
                    let pos = selectionStart;
                    while (pos < this.value.length && immutablePositions.has(pos)) pos++;
                    
                    if (pos < this.value.length && !immutablePositions.has(pos)) {
                        const newValue = this.value.substring(0, pos) + '_' + this.value.substring(pos + 1);
                        this.value = newValue;
                        setCursorPosition(this, selectionStart);
                    }
                } else {
                    let hasImmutable = false;
                    for (let i = selectionStart; i < selectionEnd; i++) {
                        if (immutablePositions.has(i)) {
                            hasImmutable = true;
                            break;
                        }
                    }
                    
                    if (!hasImmutable) {
                        setTimeout(() => {
                            handleInput.call(this, { target: this });
                        }, 0);
                    }
                }
            }, 0);
            return;
        }

        // Блокировка ввода не-цифр (через handleInput)
        if (!allowedChars.has(e.key)) {
            setTimeout(() => {
                handleInput.call(this, { target: this });
            }, 0);
        }
    }

    function setCursorPosition(input, pos) {
        if (pos === undefined) {
            // Устанавливаем курсор на первую свободную позицию
            pos = 0;
            while (pos < input.value.length && 
                  (immutablePositions.has(pos) || input.value[pos] !== '_')) {
                pos++;
            }
        }
        input.setSelectionRange(pos, pos);
    }

    if (!inputElement.value) {
        inputElement.value = mask;
    } else {
        let value = inputElement.value.replace(/^375/, '');
        let newValue = '';
        let digitIndex = 0;
        
        for (let i = 0; i < mask.length; i++) {
            if (immutablePositions.has(i)) {
                newValue += immutableChars[i];
            } else {
                // Берем цифры из существующего значения по порядку
                while (digitIndex < value.length && !allowedChars.has(value[digitIndex])) {
                    digitIndex++;
                }
                
                if (digitIndex < value.length) {
                    newValue += value[digitIndex];
                    digitIndex++;
                } else {
                    newValue += '_';
                }
            }
        }
        inputElement.value = newValue;
    }

    inputElement.addEventListener('input', handleInput);
    inputElement.addEventListener('keydown', handleKeyDown);
    inputElement.addEventListener('click', () => setStartPos(inputElement));
    inputElement.addEventListener('focus', () => setStartPos(inputElement));
    
    // Возвращаем объект с методами для управления маской
    return {
        destroy: function() {
            inputElement.removeEventListener('input', handleInput);
            inputElement.removeEventListener('keydown', handleKeyDown);
        }
    };
}

function setStartPos(input) {
    const firstEmptyPos = input.value.indexOf('_');
    const cursorPos = firstEmptyPos >= 0 ? firstEmptyPos : input.value.length;
    input.setSelectionRange(cursorPos, cursorPos);
}