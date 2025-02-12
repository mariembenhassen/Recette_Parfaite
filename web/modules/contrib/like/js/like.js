(function ($, Drupal, drupalSettings) {
  'use strict'

  Drupal.behaviors.like = {
    attach: function (context, settings) {

      $('input[name="like_toggle"]').change(function () {
        $(this).prop('disabled', true)
        const $wrapper = $(this).closest('.like--wrapper')
        const entityType = $wrapper.data('entityType')
        const entityId = $wrapper.data('entityId')
        const likeSettings = drupalSettings.like[`${entityType}:${entityId}`]
        const liked = $(this).prop('checked')
        const $siblings = $('.like--wrapper[data-entity-type="' + entityType + '"][data-entity-id="' + entityId + '"]')
        const $otherSiblings = $siblings.not($(this).closest('.like--wrapper').get(0))
        $siblings.css('pointer-events','none');

        $siblings.find('input[name="like_toggle"]').prop('checked', liked)
        const label = liked === true ? likeSettings['liked_state'] : likeSettings['default_state']
        $siblings.find('.like-txt').text(label)

        let numOfLikes = Number($wrapper.find('input[name="likes"]').val())
        if (liked) {
          numOfLikes++
        }
        else  {
          numOfLikes--
        }
        $siblings.find('.like-num > span').text(numOfLikes)
        $otherSiblings.find('input[name="likes"]').val(numOfLikes)

        $siblings.attr('data-like-loaded', true)
      })

      if ('IntersectionObserver' in window) {
        const options = {
          root: null,
          rootMargin: '100px 0px',
          threshold: 0.01
        }
        const observer = new IntersectionObserver(function (entries) {
          entries.map((entry) => {
            if (entry.isIntersecting &&
                entry.target.dataset.entityType &&
                entry.target.dataset.entityId &&
                !entry.target.dataset.likeLoaded) {
              const likeSettings = drupalSettings.like[`${entry.target.dataset.entityType}:${entry.target.dataset.entityId}`]
              $.get({
                url: Drupal.url(`like/${entry.target.dataset.entityType}/${entry.target.dataset.entityId}`),
                success: function (data) {
                  if (data.likes || data.likes === 0) {
                    $(entry.target).find('.like-num > span').text(Number(data.likes))
                    $(entry.target).find('input[name="likes"]').val(Number(data.likes))
                    let cookies = Cookies.get('Drupal.visitor.like')
                    if (cookies) {
                      cookies = JSON.parse(cookies)
                      const cookieValue = cookies[entry.target.dataset.entityType].includes(entry.target.dataset.entityId) || false
                      $(entry.target).find('input[name="like_toggle"]').prop('checked', cookieValue)
                      const label = cookieValue === true ? likeSettings['liked_state'] : likeSettings['default_state']
                      $(entry.target).find('.like-txt').text(label)
                    }
                  }
                },
                dataType: 'json'
              })

              observer.unobserve(entry.target)
            }
          })
        }, options)

        const likeBtns = Array.from(document.getElementsByClassName('like--wrapper'))
        for (let likeBtn of likeBtns) {
          observer.observe(likeBtn)
        }
      }
      else {
        // No autorefresh when no observer support.
      }
    }
  }
})(jQuery, Drupal, drupalSettings)
