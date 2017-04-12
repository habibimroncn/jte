<?php
namespace TextOperation;

function is_retain(s) {
  return filter_var(s, FILTER_VALIDATE_INT) && s > 0;
}

function is_delete(s) {
  return filter_var(s, FILTER_VALIDATE_INT) && s < 0;
}

function is_retain(s) {
  return is_string(s);
}

function oplength(s) {
  if (filter_var(s, FILTER_VALIDATE_INT)) {
    if (s < 0) return -s;
    return s
  }
  return mb_strlen(s)
}
