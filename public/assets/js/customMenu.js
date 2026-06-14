"use strict"

$(document).ready(() => {
  setContextMenu()

  const modals = document.querySelectorAll('.modal')
  modals.forEach(modal => {
    modal.addEventListener('hidden.bs.modal', () => {
      const forms = modal.querySelectorAll('form')
      forms.forEach(form => {
        form.classList.remove('was-validated')
      });
    })
  });
})

window.closeShift = function(btn) {
  const labelContent = 'Вы действительно хотите закрыть смену?'

  const bodyContent = `
  <form class="d-none" name="closeShift" id="closeShift">
    <input type="number" class="form-control" value="0" name="id">
  </form>
  `

  const footerContent = `
    <button type="button" class="btn btn-lg btn-secondary rounded-4" data-bs-dismiss="modal">Отмена</button>
    <button type="submit" class="btn btn-lg btn-danger rounded-4" form="closeShift">Закрыть смену</button>
  `
  confirmationModal(labelContent, bodyContent, footerContent, btn.dataset.update, btn.dataset.action)
}

window.openShift = function(btn) {
  const labelContent = 'Вы действительно хотите открыть смену?'

  const bodyContent = `
  <form class="d-none" name="openShift" id="openShift">
    <input type="number" class="form-control" value="${btn.dataset.id}" name="id">
    <input type="number" class="form-control" value="${btn.dataset.shortname}" name="shortname">
  </form>
  `

  const footerContent = `
    <button type="button" class="btn btn-lg btn-secondary rounded-4" data-bs-dismiss="modal">Отмена</button>
    <button type="submit" class="btn btn-lg btn-danger rounded-4" form="openShift">Открыть смену</button>
  `
  confirmationModal(labelContent, bodyContent, footerContent, btn.dataset.update, btn.dataset.action)
}

// Объявляем функции в глобальной области видимости
window.confirmation = function(id, action) {
  let element = document.querySelector('[data-action="'+ action +'"][data-id="'+ id +'"]')
  if (action === 'resetPassword' || action === 'blockUser' || action === 'unblockUser' || action === 'removeUser') {
    element = document.querySelector('[data-action="User"][data-id="'+ id +'"]')
  }
  const data = element.dataset

  let labelContent = ''
  let bodyContent = ''
  let footerContent = ''

  if (action === 'Service') {
    labelContent = 'Вы действительно хотите удалить услугу?'

    action="removeService"

    bodyContent = `
      <div class="d-flex flex-column justify-content-center"><span class="text-center fs-5">${data.categoryName}</span><span class="badge text-bg-secondary fs-5 mt-2">${data.name}</span></div>
      <form class="d-none" name="removeService" id="removeService">
        <input type="number" class="form-control" value="${parseInt(data.id)}" name="id">
        <input type="text" class="form-control" value="${data.name}" name="name">
      </form>
    `
    footerContent = `
      <button type="button" class="btn btn-lg btn-secondary rounded-4" data-bs-dismiss="modal">Отмена</button>
      <button type="submit" class="btn btn-lg btn-danger rounded-4" form="removeService">Удалить</button>
    `
  }

  if (action === 'Category') {
    labelContent = 'Вы действительно хотите удалить категорию?'

    action="removeCategory"

    bodyContent = `
      <div class="d-flex flex-column justify-content-center"><span class="badge text-bg-secondary fs-5 mt-2">${data.name}</span></div>
      <form class="d-none" name="removeCategory" id="removeCategory">
        <input type="number" class="form-control" value="${parseInt(data.id)}" name="id">
        <input type="text" class="form-control" value="${data.name}" name="name">
      </form>
    `
    footerContent = `
      <button type="button" class="btn btn-lg btn-secondary rounded-4" data-bs-dismiss="modal">Отмена</button>
      <button type="submit" class="btn btn-lg btn-danger rounded-4" form="removeCategory">Удалить</button>
    `
  }

  if (action === 'Client') {
    labelContent = 'Вы действительно хотите удалить клиента?'

    action="removeClient"

    bodyContent = `
      <div class="d-flex flex-column justify-content-center"><span class="badge text-bg-secondary fs-5 mt-2">${data.name}<br>${data.phone}</span></div>
      <form class="d-none" name="removeClient" id="removeClient">
        <input type="number" class="form-control" value="${parseInt(data.id)}" name="id">
        <input type="text" class="form-control" value="${data.name}" name="name">
      </form>
    `
    footerContent = `
      <button type="button" class="btn btn-lg btn-secondary rounded-4" data-bs-dismiss="modal">Отмена</button>
      <button type="submit" class="btn btn-lg btn-danger rounded-4" form="removeClient">Удалить</button>
    `
  }

  if (action === 'ClientGroup') {
    labelContent = 'Вы действительно хотите удалить группу?'

    action="removeClientGroup"

    bodyContent = `
      <div class="d-flex flex-column justify-content-center"><span class="badge text-bg-secondary fs-5 mt-2">${data.name}</span></div>
      <form class="d-none" name="removeClientGroup" id="removeClientGroup">
        <input type="number" class="form-control" value="${parseInt(data.id)}" name="id">
        <input type="text" class="form-control" value="${data.name}" name="name">
      </form>
    `
    footerContent = `
      <button type="button" class="btn btn-lg btn-secondary rounded-4" data-bs-dismiss="modal">Отмена</button>
      <button type="submit" class="btn btn-lg btn-danger rounded-4" form="removeClientGroup">Удалить</button>
    `
  }

  if (action === 'removeUser') {
    labelContent = 'Вы действительно хотите удалить пользователя?'

    action="removeUser"

    bodyContent = `
    <span class="badge text-bg-secondary fs-5 mt-2">${data.name}</span></div>
    <form class="d-none" name="removeUser" id="removeUser">
      <input type="number" class="form-control" value="${parseInt(data.id)}" name="id">
    </form>
    `

    footerContent = `
      <button type="button" class="btn btn-lg btn-secondary" data-bs-dismiss="modal">Отмена</button>
      <button type="submit" class="btn btn-lg btn-danger" form="removeUser">Удалить</button>
    `
  }

  if (action === 'resetPassword') {
    labelContent = 'Сбросить пароль пользователя?'

    action="resetPassword"

    data.update = ''

    bodyContent = `
    <span class="badge text-bg-secondary fs-5 mt-2">${data.name}</span></div>
    <form class="d-none" name="resetPassword" id="resetPassword">
      <input type="number" class="form-control" value="${parseInt(data.id)}" name="id">
    </form>
    `

    footerContent = `
      <button type="button" class="btn btn-lg btn-secondary" data-bs-dismiss="modal">Отмена</button>
      <button type="submit" class="btn btn-lg btn-danger" form="resetPassword">Сбросить</button>
    `
  }

  if (action === 'blockUser') {
    labelContent = 'Заблокировать пользователя?'

    action="blockUser"

    bodyContent = `
    <span class="badge text-bg-secondary fs-5 mt-2">${data.name}</span></div>
    <form class="d-none" name="blockUser" id="blockUser">
      <input type="number" class="form-control" value="${parseInt(data.id)}" name="id">
    </form>
    `

    footerContent = `
      <button type="button" class="btn btn-lg btn-secondary" data-bs-dismiss="modal">Отмена</button>
      <button type="submit" class="btn btn-lg btn-danger" form="blockUser">Заблокировать</button>
    `
  }

  if (action === 'unblockUser') {
    labelContent = 'Разблокировать пользователя?'

    action="unblockUser"

    bodyContent = `
    <span class="badge text-bg-secondary fs-5 mt-2">${data.name}</span></div>
    <form class="d-none" name="unblockUser" id="unblockUser">
      <input type="number" class="form-control" value="${parseInt(data.id)}" name="id">
    </form>
    `

    footerContent = `
      <button type="button" class="btn btn-lg btn-secondary" data-bs-dismiss="modal">Отмена</button>
      <button type="submit" class="btn btn-lg btn-danger" form="unblockUser">Разблокировать</button>
    `
  }

  if (action === 'Sale') {
    labelContent = 'Вы действительно хотите удалить продажу?'

    action="removeSale"

    bodyContent = `
    <span class="badge text-bg-secondary fs-5 mt-2">#${data.id}</span></div>
    <form class="d-none" name="removeSale" id="removeSale">
      <input type="number" class="form-control" value="${parseInt(data.id)}" name="id">
    </form>
    `

    footerContent = `
      <button type="button" class="btn btn-lg btn-secondary" data-bs-dismiss="modal">Отмена</button>
      <button type="submit" class="btn btn-lg btn-danger" form="removeSale">Удалить</button>
    `
  }

  if (action === 'Movement') {
    labelContent = 'Вы действительно хотите удалить движение?'

    action="removeMovement"

    bodyContent = `
    <span class="badge text-bg-secondary fs-5 mt-2">#${data.id}</span></div>
    <form class="d-none" name="removeMovement" id="removeMovement">
      <input type="number" class="form-control" value="${parseInt(data.id)}" name="id">
    </form>
    `

    footerContent = `
      <button type="button" class="btn btn-lg btn-secondary" data-bs-dismiss="modal">Отмена</button>
      <button type="submit" class="btn btn-lg btn-danger" form="removeMovement">Удалить</button>
    `
  }

  confirmationModal(labelContent, bodyContent, footerContent, data.update, action)
}

window.updateElement = function(id, action) {
  const element = document.querySelector('[data-action="'+ action +'"][data-id="'+ id +'"]')
  const data = element.dataset
  const formName = data.form
  const form = document.forms[formName]
  const formAction = 'update' + data.action
  const update = data.update
  const modal = document.querySelector('#' + formName + 'Modal')
  const modalLabel = document.querySelector('#' + formName + 'Label')
  const formPhones = form.querySelectorAll('[type="tel"]')
  let params = []

  if (formName === 'updateCategory') {
    params['id'] = data.id
    params['name'] = data.name
    modalLabel.innerHTML = 'Редактирование категории'
  }

  if (formName === 'updateService') {
    params['id'] = data.id
    params['category_id'] = data.categoryId
    params['name'] = data.name
    params['price'] = data.price
    params['completion_time'] = data.completion
    params['warranty_days'] = data.warranty
    params['description'] = data.description
    modalLabel.innerHTML = 'Редактирование услуги'
  }

  if (formName === 'updateRole') {
    params['id'] = data.id
    params['name'] = data.name
    params['code'] = data.code
    params['description'] = data.description
    modalLabel.innerHTML = 'Редактирование роли пользователей'
  }

  if (formName === 'updateUser') {
    params['id'] = data.id
    params['role'] = data.role
    params['post'] = data.post
    params['full_name'] = data.fullName
    params['email'] = data.email
    params['phone'] = data.phone
    modalLabel.innerHTML = 'Редактирование пользователя <strong>' + data.name + '<strong>'
  }

  if (formName === 'updateClientGroup') {
    params['id'] = data.id
    params['name'] = data.name
    params['discount'] = data.discount
    modalLabel.innerHTML = 'Редактирование группы клиентов'
  }

  if (formName === 'updateClient') {
    params['id'] = data.id
    params['name'] = data.name
    params['phone'] = data.phone
    params['type'] = data.type
    params['group'] = data.group
    params['discount'] = data.discount
    params['legal_form'] = data.legalForm
    params['legal_name'] = data.legalName
    params['legal_unp'] = data.legalUnp
    params['legal_address'] = data.legalAddress
    params['legal_email'] = data.legalEmail
    params['legal_phone'] = data.legalPhone
    params['legal_bank'] = data.legalBank
    params['legal_check'] = data.legalCheck
    params['legal_bic'] = data.legalBic
    params['legal_bank_address'] = data.legalBankAddress
    params['legal_post'] = data.legalPost
    params['legal_signatory'] = data.legalSignatory
    params['legal_document'] = data.legalDocument

    selectClientTypeInUpdate(data.type)

    modalLabel.innerHTML = 'Редактирование клиента'
  }

  if (formName === 'updateMovement') {
    params['id'] = data.id
    params['type'] = data.type
    params['footing'] = data.footing
    params['method'] = data.method
    params['summ'] = data.summ

    modalLabel.innerHTML = 'Редактирование движения'
  }

  const entries = Object.entries(params);

  entries.forEach(([key, value]) => {
    form[key].value = value
  });

  if (formPhones && formPhones.length > 0) {
    formPhones.forEach(element => {
      maskedPhone(element)
    });
  }

  new bootstrap.Modal(modal).show()

  form.onsubmit = function(e) {
    e.preventDefault();
    sendFormData(formAction, this).then(result => {
      if (result.success === true) {
        bootstrap.Modal.getInstance(document.getElementById(formName + 'Modal')).hide()
        form.reset()
        updateSections(update)
        ShowMsg(result.message, 'success')
      } else {
        ShowMsg(result.message, 'error')
      }
    });
    return false;
  };
}

window.confirmationModal = function(label, body, footer, update = null, action) {
  const confirmationModal = document.querySelector('#confirmationModal');
  const modalLabel = confirmationModal.querySelector('#confirmationLabel')
  const modalBody = confirmationModal.querySelector('.modal-body')
  const modalFooter = confirmationModal.querySelector('.modal-footer')

  modalLabel.innerHTML = label
  modalBody.innerHTML = body
  modalFooter.innerHTML = footer

  new bootstrap.Modal(confirmationModal).show()

  const confirmationForm = document.forms[action]
  confirmationForm.addEventListener('submit', (e) => {
    e.preventDefault()
    sendFormData(action, confirmationForm).then(result => {
      if (result.success === true) {
        bootstrap.Modal.getInstance(document.getElementById('confirmationModal')).hide()
        confirmationForm.reset()
        ShowMsg(result.message, 'success')
        if (update !== null) {
          updateSections(update)
        }
      } else {
        ShowMsg(result.message, 'error')
        bootstrap.Modal.getInstance(document.getElementById('confirmationModal')).hide()
      }
    })
  })
}

function setContextMenu() {
  const customContextList = ['servicesList', 'categoriesList', 'clientsList', 'clientGroupsList', 'userRolesList', 'usersList', 'salesList', 'movementsList'];
  const customContextBlocks = customContextList.map(name => document.getElementById(name));
  
  customContextBlocks.forEach(block => {
    if (block) {
      block.removeEventListener('contextmenu', handleContextMenu);
    }
  });

  customContextBlocks.forEach(block => {
    if (block) {
      block.addEventListener('contextmenu', function(e) {
        e.preventDefault();
      });
      
      block.addEventListener('contextmenu', handleContextMenu);
    }
  });
}

function handleContextMenu(event) {
  if (event.target.closest('svg[data-bs-toggle="popover"]')) {
    return;
  }

  const element = event.target.closest('[data-action]');
  if (!element) return;

  showContextMenu(event, element.dataset);
}

function showContextMenu(event, data) {
  // Удаляем старое меню, если есть
  const oldMenu = document.getElementById('customContextMenu');
  if (oldMenu) oldMenu.remove();
  
  // Создаем новое меню
  const menu = document.createElement('div');
  menu.id = 'customContextMenu';
  menu.className = 'custom-context-menu shadow rounded-4 bg-body position-fixed';
  menu.style.left = `${event.pageX}px`;
  menu.style.top = `${event.pageY}px`;
  menu.style.zIndex = '1060';

  let menuButtons = ''

  if (data.action === 'Service' || data.action === 'Category' || data.action === 'ClientGroup' || data.action === 'Role' || data.action === 'Movement') {
    menuButtons = `
      <div class="list-group border-0" style="min-width: 200px;">
        <button type="button" class="list-group-item list-group-item-action border-0 text-info" 
                onclick="window.updateElement(${data.id}, '${data.action}')">
          <i class="bi bi-pencil me-2"></i>Редактировать
        </button>
        <button type="button" class="list-group-item list-group-item-action border-0 text-danger" 
          onclick="window.confirmation(${parseInt(data.id)}, '${data.action}')">
          <i class="bi bi-trash me-2"></i>Удалить
        </button>
      </div>
    `
  }

  if (data.action === 'Client') {
      menuButtons = `
        <div class="list-group border-0" style="min-width: 200px;">
          <a href="tel:+${data.phone}" class="list-group-item list-group-item-action border-0">
            <i class="bi bi-telephone me-2"></i>${phoneFormat(data.phone)}
          </a>
          ${data.legalEmail && data.legalEmail.trim() ? `
          <a href="mailto:${data.legalEmail}" class="list-group-item list-group-item-action border-0">
            <i class="bi bi-envelope-at me-2"></i>${data.legalEmail}
          </a>
          ` : ''}
          <hr class="m-1">
          <button type="button" class="list-group-item list-group-item-action border-0 text-info" 
                  onclick="window.updateElement(${data.id}, '${data.action}')">
            <i class="bi bi-pencil me-2"></i>Редактировать
          </button>
          <button type="button" class="list-group-item list-group-item-action border-0 text-danger" 
            onclick="window.confirmation(${parseInt(data.id)}, '${data.action}')">
            <i class="bi bi-trash me-2"></i>Удалить
          </button>
        </div>
      `;
  }

  if (data.action === 'User') {
      menuButtons = `
        <div class="list-group border-0" style="min-width: 200px;">
          <a href="tel:+${data.phone}" class="list-group-item list-group-item-action border-0">
            <i class="bi bi-telephone me-2"></i>${phoneFormat(data.phone)}
          </a>
          ${data.email && data.email.trim() ? `
          <a href="mailto:${data.email}" class="list-group-item list-group-item-action border-0">
            <i class="bi bi-envelope-at me-2"></i>${data.email}
          </a>
          ` : ''}
          <hr class="m-1">
          <button type="button" class="list-group-item list-group-item-action border-0" 
                  onclick="window.confirmation(${data.id}, 'resetPassword')">
            <i class="bi bi-repeat me-2"></i>Сбросить пароль
          </button>
          ${data.active === 1 ? `
          <button type="button" class="list-group-item list-group-item-action text-warning border-0" 
                  onclick="window.confirmation(${data.id}, 'blockUser')">
            <i class="bi bi-x-circle me-2"></i>Заблокировать
          </button>
          ` : `
          <button type="button" class="list-group-item list-group-item-action text-success border-0" 
                  onclick="window.confirmation(${data.id}, 'unblockUser')">
            <i class="bi bi-x-circle me-2"></i>Разблокировать
          </button>
          `}
          <hr class="m-1">
          <button type="button" class="list-group-item list-group-item-action border-0 text-info" 
                  onclick="window.updateElement(${data.id}, '${data.action}')">
            <i class="bi bi-pencil me-2"></i>Редактировать
          </button>
          <button type="button" class="list-group-item list-group-item-action border-0 text-danger" 
            onclick="window.confirmation(${data.id}, 'removeUser')">
            <i class="bi bi-trash me-2"></i>Удалить
          </button>
        </div>
      `;
  }

  if (data.action === 'Sale') {
      menuButtons = `
        <div class="list-group border-0" style="min-width: 200px;">
          <a href="tel:+${data.phone}" class="list-group-item list-group-item-action border-0">
            <i class="bi bi-telephone me-2"></i>${phoneFormat(data.phone)}
          </a>
          <hr class="m-1">
          <button type="button" class="list-group-item list-group-item-action border-0 text-danger" 
            onclick="window.confirmation(${parseInt(data.id)}, '${data.action}')">
            <i class="bi bi-trash me-2"></i>Удалить
          </button>
        </div>
      `;
  }
  
  menu.innerHTML = menuButtons
  
  document.body.appendChild(menu);
  
  // Закрытие меню при клике вне его
  setTimeout(() => {
      document.addEventListener('click', function closeMenu() {
      menu.remove();
      document.removeEventListener('click', closeMenu);
    });
  }, 10);
}

function phoneFormat(number) {
    if (!number) return '';
    
    const strNumber = String(number);
    return `+375 (${strNumber.slice(-9, -7)}) ${strNumber.slice(-7, -4)}-${strNumber.slice(-4, -2)}-${strNumber.slice(-2)}`;
}