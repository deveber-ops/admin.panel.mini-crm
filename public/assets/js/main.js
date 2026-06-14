"use strict"

const now = new Date();
const year = now.getFullYear();
const month = String(now.getMonth() + 1).padStart(2, '0');
const day = String(now.getDate()).padStart(2, '0');
const hours = String(now.getHours()).padStart(2, '0');
const minutes = String(now.getMinutes()).padStart(2, '0');
const datetimeString = `${year}-${month}-${day}T${hours}:${minutes}`;

function setNowDateTime(event) {
  const modal = event.target;
  modal.querySelectorAll('[type="datetime-local"]').forEach(input => {
    input.value = datetimeString;
  });
}

$(document).ready(() => {
  document.querySelectorAll('[type="tel"]').forEach(input => {
    maskedPhone(input)
  });
  const forms = document.querySelectorAll('.needs-validation')

  Array.from(forms).forEach(form => {
  form.addEventListener('submit', event => {
      if (!form.checkValidity()) {
        event.preventDefault()
        event.stopPropagation()
      }

      form.classList.add('was-validated')
  }, false)
  })
  loadPopovers()
  setStartAndEndDate()
})

document.addEventListener('DOMContentLoaded', function() {
  const loader = document.getElementById('pageLoader');
  const links = document.querySelectorAll('a');

  // Показываем лоадер при клике на любую ссылку
  links.forEach(link => {
    link.addEventListener('click', function(e) {
      // Если ссылка ведёт на другой сайт, не показываем лоадер
      if (this.hostname !== window.location.hostname) return;
      
      e.preventDefault(); // Отменяем стандартное поведение
      const href = this.getAttribute('href');
      
      loader.classList.remove('d-none'); // Показываем лоадер
      loader.classList.add('show');
      
      // Загружаем новую страницу после небольшой задержки (для демонстрации)
      setTimeout(() => {
        window.location.href = href;
      }, 100);
    });
  });

  // Скрываем лоадер после загрузки страницы (на случай, если он остался)
  window.addEventListener('load', function() {
    setTimeout(() => {
      loader.classList.add('d-none');
      loader.classList.remove('show');
    }, 100);
  });
});

function loadPopovers() {
  const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]')
  const popoverList = [...popoverTriggerList].map(popoverTriggerEl => new bootstrap.Popover(popoverTriggerEl))
}

const filterFormsList = ['filterMovements', 'salesFilter'];
const filterForms = filterFormsList.map(name => document.forms[name]);

function setStartAndEndDate() {
  const startDate = new Date(now.getFullYear(), now.getMonth(), 2);
  const endDate = `${year}-${month}-${day}`;

  filterForms.forEach(form => {
    if (form) {
      form.start_date.value = startDate.toISOString().split('T')[0];
      form.end_date.value = endDate
    }
  });
}

filterForms.forEach(form => {
  if (form) {
    form.addEventListener('submit', (e) => {
      e.preventDefault()
    })
    form.addEventListener('input', (event) => handleFilter(event, form))
    form.addEventListener('change', (event) => handleFilter(event, form))
    form.querySelector('#resetForm').addEventListener('click', (event) => {
      form.reset()
      handleFilter(event, form)
    })
  }
});

const handleFilter = debounce((event, form) => {
  const update = form.dataset.update;
  
  // Создаем FormData из формы
  const formData = new FormData(form);
  
  const formDataToArray = (data) => {
    const array = {};
    for (let [key, value] of data.entries()) {
      array[key] = value;
    }
    return array;
  };

  const filterArray = JSON.stringify(formDataToArray(formData));
  
  updateSections(update, filterArray);
}, 50);

async function sendFormData(url, formElement) {
  try {
      const formData = new FormData(formElement);
      formData.append('json', 'true');

      const response = await fetch(url, {
          method: 'POST',
          body: formData,
      });

      if (!response.ok) {
          throw new Error(`Ошибка HTTP: ${response.status}`);
      }

      return await response.json();
  } catch (error) {
      throw error;
  }
}

async function sendData(url, data) {
  try {
      const response = await fetch(url, {
          method: 'POST',
          body: data,
      });

      if (!response.ok) {
          throw new Error(`Ошибка HTTP: ${response.status}`);
      }

      return await response.json();
  } catch (error) {
      throw error;
  }
}

/* Уведомления */

function ShowMsg(msg, status) {
  let ariaLabel;
  let svgIcon;
  let alertBlock;

  if (status === 'info') {
    status = 'alert-primary'
    svgIcon = '#info-fill'
    ariaLabel = 'Info:'
  }
  if (status === 'success') {
    status = 'alert-success'
    svgIcon = '#check-circle-fill'
    ariaLabel = 'Success:'
  } 
  if (status === 'warning') {
    status = 'alert-warning'
    svgIcon = '#exclamation-triangle-fill'
    ariaLabel = 'Warning:'
  } 
  if (status === 'error') {
    status = 'alert-danger'
    svgIcon = '#exclamation-triangle-fill'
    ariaLabel = 'Danger:'
  }
  if (status === undefined) {
    status = 'alert-danger'
    svgIcon = '#exclamation-triangle-fill'
    ariaLabel = 'Danger:'
    msg = 'Неизвестная ошибка, попробуйте позже!'
  }

  alertBlock = `<div class="alert ${status} d-flex alert-dismissible fade show mb-1 me-2 ms-2 align-items-center" role="alert">
                          <svg class="bi flex-shrink-0 me-2" fill="currentColor" role="img" aria-label="${ariaLabel}"><use xlink:href="${svgIcon}"/></svg>
                          <div>${msg}</div>
                      </div>`

  $('.alertsGroup').append(alertBlock)

  setTimeout(() => {
    document.querySelector('.alert').classList.remove('show')
    setTimeout(() => {
      document.querySelector('.alert').remove()
    }, 500)
  }, 5000)
}

/* Уведомления */

function LOADER(action, loaderID, block = null, width = null, height = null) {
  if (action === 'show') {
    let loader = document.createElement('div');
    loader.classList.value = 'd-flex justify-content-center align-items-center position-absolute top-0 start-0 end-0 bottom-0 fade z-3';
    loader.id = loaderID || Math.random().toString(36).substring(2, 12);
    loader.style.background = 'inherit';
    loader.innerHTML = `<div class="spinner-border" style="width: ${width}; height: ${height};" role="status"></div>`;
    document.querySelector(block).append(loader);
    setTimeout(() => {
      document.getElementById(loader.id).classList.add(action);
    }, 100);
    return loader.id;
  } else if (action === 'hide') {
    const loader = document.getElementById(loaderID);
    if (loader) {
      loader.classList.remove('show')
      setTimeout(() => {
        loader.remove()
      }, 100);
    } else {
      console.error('Прелоадер с таким ID не найден.');
    }
  }
}

const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))

function updateSections(names, filter = null) {
  names.split(', ').forEach(name => {
    const oldSection = document.getElementById(name);
    if (!oldSection) return; // Если секции нет, пропускаем

    // Сохраняем все классы и атрибуты
    const oldClasses = Array.from(oldSection.classList);
    const oldAttributes = {};
    Array.from(oldSection.attributes).forEach(attr => {
      oldAttributes[attr.name] = attr.value;
    });

    const data = new FormData();
    data.append('sectionName', name);
    data.append('filter', filter)

    sendData('updateSection', data).then(result => {
      // Создаем временный контейнер для разбора HTML
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = result.html;
      
      // Находим новый элемент в разобранном HTML
      const newContent = tempDiv.firstElementChild;
      
      // Восстанавливаем классы и атрибуты
      oldClasses.forEach(cls => newContent.classList.add(cls));
      for (const [name, value] of Object.entries(oldAttributes)) {
        newContent.setAttribute(name, value);
      }
      
      // Заменяем старый блок новым
      oldSection.replaceWith(newContent);
      loadPopovers();
      setContextMenu()
    });
  });
}

function updateSortSections(name, data) {
  const oldSection = document.getElementById(name);
  if (!oldSection) return; // Если секции нет, пропускаем

  // Сохраняем все классы и атрибуты
  const oldClasses = Array.from(oldSection.classList);
  const oldAttributes = {};
  Array.from(oldSection.attributes).forEach(attr => {
    oldAttributes[attr.name] = attr.value;
  });

  sendData('sortItems', data).then(result => {
    if (result.success === false) {
      ShowMsg(result.message, 'info')
    } else {
      // Создаем временный контейнер для разбора HTML
      const tempDiv = document.createElement('div');
      tempDiv.innerHTML = result.html;
      
      // Находим новый элемент в разобранном HTML
      const newContent = tempDiv.firstElementChild;
      
      // Восстанавливаем классы и атрибуты
      oldClasses.forEach(cls => newContent.classList.add(cls));
      for (const [name, value] of Object.entries(oldAttributes)) {
        newContent.setAttribute(name, value);
      }
      
      // Заменяем старый блок новым
      oldSection.replaceWith(newContent);
      loadPopovers();
      setContextMenu()
    }
  });
}

/* Обработчик форм добавления */
const insertModalsList = ['insertSaleModal'];
const insertModals = insertModalsList.map(name => document.querySelector('#'+name));

insertModals.forEach(modal => {
  if (modal) {
    modal.addEventListener('show.bs.modal', setNowDateTime)
  }
});

const insertFormsList = ['insertCategory', 'insertService', 'insertUser', 'insertRole', 'insertClientGroup', 'insertClient', 'insertSale', 'insertMovement'];
const insertForms = insertFormsList.map(name => document.forms[name]);

insertForms.forEach(form => {
  if (form) {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();

      const formAction = form.getAttribute('action')
      const formName = form.getAttribute('name')
      const update = form.getAttribute('updateSections')
      const modal = document.querySelector('#'+formName+'Modal')
      const formPhones = form.querySelectorAll('[type="tel"]')

      if (!form.checkValidity()) {
        form.reportValidity();
      } else {
          if (formName === 'insertSale') {
            modal.querySelector('#waitPaid').classList.remove('d-none')
            modal.querySelector('#waitPaid').classList.add('show')
          }
          sendFormData(formAction, form).then(result => {
            if (result.success === true) {
              form.reset()
              form.classList.remove('was-validated')
              if (formName === 'insertSale') {
                modal.querySelector('#waitPaid').classList.remove('show')
                modal.querySelector('#waitPaid').classList.add('d-none')
                form.cash.value = 0
                form.card.value = 0
                form.noncash.value = 0
                form.querySelector('#mixedInputs').classList.add('d-none')
                form.querySelector('.serviceSelect').value = ''
                const serviceForms = form.querySelectorAll('#serviceForm')
                form.querySelector('#clientInfo').classList.add('d-none')
                serviceForms.forEach((element, index) => {
                    if (index !== 0) { // Пропускаем первый элемент (индекс 0)
                        element.remove();
                    }
                });
              }
              bootstrap.Modal.getInstance(document.getElementById(formName + 'Modal')).hide()
              updateSections(update);
              if (formPhones) {
                formPhones.forEach(input => {
                  maskedPhone(input);
                });
              }
              ShowMsg(result.message, 'success')
            } else {
              ShowMsg(result.message, 'error')
              if (formName === 'insertSale') {
                modal.querySelector('#waitPaid').classList.remove('show')
                modal.querySelector('#waitPaid').classList.add('d-none')
              }
            }
          })
        }
    })
  }
});

/* Обработчик форм добавления */

/* Сортировка */

function sortItems(sortBtn) {
  let sortID = sortBtn.getAttribute('sort-id')
  const sortAction = sortBtn.getAttribute('sort-action')
  const update = sortBtn.getAttribute('updateSections')
  const allSortBtns = document.querySelectorAll('.sortBtn')
  let isActive = sortBtn.classList.contains('active');
  const data = new FormData()

  allSortBtns.forEach(btn => {
    btn.classList.remove('active')
  });

  if (!isActive) {
    sortBtn.classList.add('active');
  } else {
    sortID = null
  }

  data.append('id', sortID)
  data.append('sort', sortAction)

  updateSortSections(sortAction, data)
}

/* Сортировка услуг по категории */

/* Переключение типа клиента */

function selectClientType(btn) {
  const form = document.forms.insertClient
  const legalForm = form.querySelector('#legalForm')
  const legalInputs = legalForm.querySelectorAll('input')
  const legalSelects = legalForm.querySelectorAll('select')

  if (btn.value === 'individual') {
    legalForm.classList.add('d-none')
    legalInputs.forEach(el => {
      el.removeAttribute('required')
    });
    legalSelects.forEach(el => {
      el.removeAttribute('required')
    });
  } else {
    legalForm.classList.remove('d-none')
    legalInputs.forEach(el => {
      el.setAttribute('required', 'required')
    });
    legalSelects.forEach(el => {
      el.setAttribute('required', 'required')
    });
  }
}

function selectClientTypeInUpdate(type) {
  const form = document.forms.updateClient
  const legalForm = form.querySelector('#legalUpdateForm')
  const legalInputs = legalForm.querySelectorAll('input')
  const legalSelects = legalForm.querySelectorAll('select')

  if (type === 'individual') {
    legalForm.classList.add('d-none')
    legalInputs.forEach(el => {
      el.removeAttribute('required')
    });
    legalSelects.forEach(el => {
      el.removeAttribute('required')
    });
  } else {
    legalForm.classList.remove('d-none')
    legalInputs.forEach(el => {
      el.setAttribute('required', 'required')
    });
    legalSelects.forEach(el => {
      el.setAttribute('required', 'required')
    });
  }
}

/* Переключение типа клиента */

/* Поиск клиентов по телефону или УНП */
const searchClientsForm = document.forms.searchClients;

if (searchClientsForm) {
  const action = searchClientsForm.getAttribute('action')
  const update = searchClientsForm.getAttribute('updateSections')

  const handleSearch = debounce(() => {
    let data = new FormData()
    data.append('phone', searchClientsForm.phone.value.replace(/\D/g, ''))
    data.append('legal', searchClientsForm.legal.value)
    sendData(action, data).then(result => {
      if (result.success === true) {
        document.querySelector('#'+update).innerHTML = result.html
      } else {
        ShowMsg(result.message, 'info')
      }
    })
  }, 500);

  searchClientsForm.addEventListener('input', handleSearch)
}

function debounce(func, wait, immediate) {
  let timeout;
  return function() {
    const context = this, args = arguments;
    const later = function() {
      timeout = null;
      if (!immediate) func.apply(context, args);
    };
    const callNow = immediate && !timeout;
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
    if (callNow) func.apply(context, args);
  };
}

const dateFormatter = new Intl.DateTimeFormat('ru-RU', {
  year: 'numeric',
  month: '2-digit',
  day: '2-digit',
  hour: '2-digit',
  minute: '2-digit'
});

const insertSaleForm = document.forms.insertSale

if (insertSaleForm) {
  insertSaleForm.addEventListener('change', (e) => {
    const targetInput = e.target

    sumAllServices(targetInput.name)

    if (targetInput.name === 'pay_method') {
      updateReceiptCheck(insertSaleForm.receiptCheck, insertSaleForm.receipt, targetInput.dataset.code)
    }

    if (targetInput.name === 'receiptCheck') {
      insertSaleForm.receipt.value = insertSaleForm.receiptCheck.checked
    }
    
    if (targetInput.name === 'phone') {
      let data = new FormData()
      const servicesForm = document.forms.insertSale
      const serviceForms = servicesForm.querySelectorAll('#serviceForm')
      const clientInfoBlock = servicesForm.querySelector('#clientInfo')

      data.append('phone', targetInput.value.replace(/\D/g, ''))

      serviceForms.forEach(element => {
        const discountInput = element.querySelector('[name^="services["][name$="][discount]"]')
        discountInput.value = ''
        discountInput.removeAttribute('readonly')
        sumAllServices()
      });

      sendData('searchClients', data).then(result => {
        if (result.success === true) {
          const clientJSON = JSON.parse(result.json)[0]
          const clientName = clientJSON.type === 'legal' ? clientJSON.legal_form_code + ' "' + clientJSON.legal_name + '"' : clientJSON.name
          const clientDiscount = parseInt(clientJSON.discount)
          console.log(clientJSON.last_sale_date)
          const clientLastSale = isNaN(clientJSON.last_sale_date) ? dateFormatter.format(new Date(clientJSON.last_sale_date)).replace(',', ' - ') : 'Не найдена'
          const clientTotalSales = isNaN(clientJSON.total_sales) ? 0 : clientJSON.total_sales

          servicesForm.name.value = clientName
          servicesForm.name.setAttribute('readonly', 'readonly')
          clientInfoBlock.classList.remove('d-none')
          clientInfoBlock.querySelector('#clientTotalSales').innerHTML = 'Всего продаж <span class="badge text-bg-secondary fw-normal">'+clientTotalSales+'</span>'
          clientInfoBlock.querySelector('#clientLastSale').innerHTML = 'Последняя продажа <span class="badge text-bg-secondary fw-normal">'+clientLastSale+'</span>'
          clientInfoBlock.querySelector('#clientPersonalDiscount').innerHTML = 'Персональная скидка <span class="badge text-bg-secondary fw-normal">'+clientDiscount+' %</span>'

          serviceForms.forEach(element => {
            const discountInput = element.querySelector('[name^="services["][name$="][discount]"]')
            const currentDiscount = discountInput.value

            if (clientDiscount > currentDiscount) {
              discountInput.value = clientDiscount
              discountInput.setAttribute('data-discount', 'personal')
              discountInput.setAttribute('readonly', 'readonly')
              sumAllServices()
            }
          });
        } else {
          ShowMsg(result.message, 'info')
          servicesForm.name.removeAttribute('readonly')
          servicesForm.querySelector('#clientInfo').classList.add('d-none')
          serviceForms.forEach(element => {
            const discountInput = element.querySelector('[name^="services["][name$="][discount]"]')
            discountInput.value = ''
            discountInput.removeAttribute('readonly')
            sumAllServices()
          });
          clientInfoBlock.querySelector('#clientTotalSales').innerHTML = ''
          clientInfoBlock.querySelector('#clientLastSale').innerHTML = ''
          clientInfoBlock.querySelector('#clientPersonalDiscount').innerHTML = ''
        }
      })
    }
  })
}

function mixedPaymenth(input) {
  const currentSumm = parseFloat(insertSaleForm.summ.value).toFixed(2)
  const cashInput = insertSaleForm.cash
  const cardInput = insertSaleForm.card
  if (input.name === 'cash') {
    cardInput.value = (currentSumm - input.value).toFixed(2)
  } else if (input.name === 'card') {
    cashInput.value = (currentSumm - input.value).toFixed(2)
  }
}

function setServiceInSelect(btn) {
  const formID = parseInt(btn.dataset.id);
  const serviceID = btn.dataset.serviceid;
  const servicesForm = document.forms.insertSale;
  const serviceForm = servicesForm.querySelector('#serviceForm[data-id="' + formID + '"]');
  const serviceSelect = serviceForm.querySelector('select[name^="services["][name$="][id]"]');
  const serviceSelectBtn = serviceForm.querySelector('.serviceSelect');
  const serviceDropdown = serviceForm.querySelector('.serviceDropdown');
  const categoriesDropdown = serviceForm.querySelector('.categoriesDropdown');

  // Устанавливаем значения
  serviceSelectBtn.value = btn.dataset.category + ' - ' + btn.dataset.name;
  serviceSelect.value = serviceID;
  serviceSelect.dispatchEvent(new Event('change'));
  serviceForm.querySelector('input[name^="services["][name$="][price]"]').value = btn.dataset.price;

  // Правильное закрытие дропдаунов
  const serviceDropdownInstance = bootstrap.Dropdown.getInstance(serviceDropdown);
  const categoriesDropdownInstance = bootstrap.Dropdown.getInstance(categoriesDropdown);

  // Если экземпляры существуют - закрываем через API
  if (serviceDropdownInstance) {
    serviceDropdownInstance.hide();
    serviceSelectBtn.classList.remove('show')
  } else {
    new bootstrap.Dropdown(serviceDropdown).hide()
    serviceSelectBtn.classList.remove('show')
  }

  if (categoriesDropdownInstance) {
    categoriesDropdownInstance.hide()
  } else {
    new bootstrap.Dropdown(categoriesDropdown).hide()
  }

  sumAllServices();
}

function sumAllServices(target = null) {
  try {

    if (!insertSaleForm) {
      console.error('Форма insertSale не найдена');
      return;
    }

    if (target === 'cash' || target === 'card') {
      return
    }

    const payMethod = insertSaleForm.querySelector('input[name="pay_method"]:checked')?.dataset.code;
    const cashInput = insertSaleForm.cash
    const cardInput = insertSaleForm.card
    const noncashInput = insertSaleForm.noncash
    const mixedImputs = insertSaleForm.querySelector('#mixedInputs')
    const receipt = insertSaleForm.receipt
    const receiptCheck = insertSaleForm.receiptCheck

    const serviceForms = insertSaleForm.querySelectorAll('#serviceForm');

    let calculatedSum = 0;

    serviceForms.forEach(form => {
      try {
        const priceInput = form.querySelector('input[name^="services["][name$="][price]"]');
        const discountInput = form.querySelector('input[name^="services["][name$="][discount]"]');
        
        if (!priceInput || !discountInput) {
          console.warn('Не найдены поля price или discount в одной из форм');
          return;
        }

        const price = parseFloat(priceInput.value) || 0;
        const discount = parseFloat(discountInput.value) || 0;
        
        // Проверка на корректность скидки (0-100%)
        const validDiscount = Math.min(100, Math.max(0, discount));
        
        calculatedSum += price - (price * (validDiscount / 100));
      } catch (error) {
        console.error('Ошибка при обработке формы услуги:', error);
      }
    });

    // Обновляем значение с фиксированными 2 знаками после запятой
    insertSaleForm.summ.value = calculatedSum.toFixed(2);

    if (payMethod === 'cash' || payMethod === 'card' || payMethod === 'noncash') {
      mixedImputs.classList.add('d-none')
      cashInput.setAttribute('readonly', 'readonly')
      cardInput.setAttribute('readonly', 'readonly')
      noncashInput.setAttribute('readonly', 'readonly')
    } else if (payMethod === 'mixed') {
      mixedImputs.classList.remove('d-none')
      cashInput.removeAttribute('readonly')
      cardInput.removeAttribute('readonly')
    }

    payMethod === 'cash' ? cashInput.value = calculatedSum.toFixed(2) : cashInput.value = 0
    payMethod === 'card' ? cardInput.value = calculatedSum.toFixed(2) : cardInput.value = 0
    payMethod === 'noncash' ? noncashInput.value = calculatedSum.toFixed(2) : noncashInput.value = 0
    payMethod === 'mixed' ? (cardInput.value = (calculatedSum/2).toFixed(2), cashInput.value = (calculatedSum/2).toFixed(2)) : ''
    
    // Возвращаем числовое значение, если нужно использовать в других вычислениях
    return parseFloat(calculatedSum.toFixed(2));
    
  } catch (error) {
    console.error('Ошибка в функции sumAllServices:', error);
    return 0;
  }
}

function updateReceiptCheck(ckeckbox, input, payMethod) {
  ckeckbox.checked = false;
  ckeckbox.readOnly = false;
  ckeckbox.style.pointerEvents = 'auto';
  ckeckbox.parentElement.style.opacity = '1';
  input.value = false

  if (payMethod === 'card' || payMethod === 'mixed') {
    ckeckbox.checked = true;
    ckeckbox.readOnly = true;
    ckeckbox.style.pointerEvents = 'none';
    ckeckbox.parentElement.style.opacity = '0.7';
    input.value = true
  }

  if (payMethod === 'noncash') {
    ckeckbox.checked = false;
    ckeckbox.readOnly = true;
    ckeckbox.style.pointerEvents = 'none';
    ckeckbox.parentElement.style.opacity = '0.7';
    input.value = false
  }
}

function clearDevice(btn) {
  const formID = parseInt(btn.dataset.id)
  const servicesForm = document.forms.insertSale
  const serviceForm = servicesForm.querySelector('#serviceForm[data-id="'+formID+'"]')
  const serviceDevice = serviceForm.querySelector('input[name^="services["][name$="][device]"]')

  serviceDevice.value = ''
}

function insertServiceInSaleForm(btn) {
  let currentID = parseInt(btn.dataset.id) || 0
  const nextID = currentID + 1

  const saleServiceForms = document.querySelector('#saleServiceForms')
  const serviceFormBlock = saleServiceForms.querySelector('#serviceForm')

  if (!serviceFormBlock) {
    console.error('Элемент #serviceForm не найден!')
    return;
  }

  serviceFormBlock.querySelector('[data-action="remove"]').classList.remove('d-none')

  // Клонируем блок
  const clonedBlock = serviceFormBlock.cloneNode(true)
  clonedBlock.dataset.id = nextID
  clonedBlock.classList.add('mt-3')
  clonedBlock.querySelector('[data-action="remove"]').classList.remove('d-none')
  clonedBlock.querySelector('.serviceSelect').value = ''
  clonedBlock.querySelector('select[name^="services["][name$="][id]"]').setAttribute('name', 'services['+nextID+'][id]')
  clonedBlock.querySelector('input[name^="services["][name$="][price]"]').setAttribute('name', 'services['+nextID+'][price]')
  clonedBlock.querySelector('input[name^="services["][name$="][discount]"]').setAttribute('name', 'services['+nextID+'][discount]')
  clonedBlock.querySelector('input[name^="services["][name$="][device]"]').setAttribute('name', 'services['+nextID+'][device]')

  clonedBlock.querySelectorAll('[data-id]').forEach(element => {
    element.dataset.id = nextID
  })

  clonedBlock.querySelectorAll('input').forEach(input => {
    const personalDiscount = !!(input.dataset.discount && input.dataset.discount === 'personal')
    if (input.getAttribute('name') !== 'device' && (personalDiscount === false && input.getAttribute('name') !== 'discount')) {
      input.value = ''
    } else if (personalDiscount === true && input.getAttribute('name') !== 'discount') {
      input.setAttribute('readonly', 'readonly')
    }
  })

  saleServiceForms.appendChild(clonedBlock)

  btn.dataset.id = nextID
}

function removeServiceForm(btn) {
  const saleServiceForms = document.querySelector('#saleServiceForms');
  const allServiceForms = saleServiceForms.querySelectorAll('#serviceForm');
  const currentID = parseInt(btn.dataset.id);
  const currentForm = saleServiceForms.querySelector(`#serviceForm[data-id="${currentID}"]`);

  // Удаляем текущую форму
  currentForm.remove();
  sumAllServices()

  // Получаем обновленный список форм после удаления
  const updatedForms = saleServiceForms.querySelectorAll('#serviceForm');

  // Если осталась только одна форма - скрываем её кнопку удаления
  if (updatedForms.length === 1) {
    updatedForms[0].querySelector('[data-action="remove"]').classList.add('d-none');
    // Убираем margin-top у последней формы, если он был
    updatedForms[0].classList.remove('mt-3');
  }
}

function changeCommission(input) {
  const movementID = input.dataset.id
  const commissionValue = input.value
  const update = input.dataset.update

  let data = new FormData()
  data.append('id', movementID)
  data.append('value', commissionValue)
  
  sendData('changeMovementCommission', data).then(result => {
    if (result.success === true) {
      ShowMsg(result.message, 'success')
      updateSections(update)
    } else {
      ShowMsg(result.message, 'danger')
    }
  })
}

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('/public/assets/js/sw.js')
      .then(registration => {
        console.log('ServiceWorker registration successful');
      })
      .catch(err => {
        console.log('ServiceWorker registration failed: ', err);
      });
  });
}

self.addEventListener('sync', event => {
  if (event.tag === 'mySync') {
    event.waitUntil(doBackgroundSync());
  }
});

self.addEventListener('push', event => {
  const title = 'Новое уведомление';
  const options = {
    body: event.data.text(),
    icon: '/icons/icon-192x192.png',
    badge: '/icons/icon-72x72.png'
  };
  event.waitUntil(self.registration.showNotification(title, options));
});

