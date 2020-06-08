<?php

namespace App\Classes {

    class UserEntity
    {
        /*
         * The constructor & pwd setter automatically sha512 hashes the password.
         * Because of this, the entity can be saved to the DB as is & compared to the DB pwd value.
         */
        private $mail;
        private $name;
        private $firstName;
        private $birthIsoDate;
        private $pwd;
        private $isDeleted;

        public function __construct($mail, $name, $firstName, $birthIsoDate, $pwd, $isDeleted = false)
        {
            $this->mail = $mail;
            $this->name = $name;
            $this->firstName = $firstName;
            $this->birthIsoDate = $birthIsoDate;
            $this->pwd = hash('sha512', $pwd);
            $this->isDeleted = $isDeleted;
        }

        public function toArray() {
            return [
                'mail' => $this->mail,
                'name' => $this->name,
                'firstName' => $this->firstName,
                'birthIsoDate' => $this->birthIsoDate,
                'pwd' => $this->pwd,
                'isDeleted' => $this->isDeleted,
            ];
        }


        /**
         * @return mixed
         */
        public function getMail()
        {
            return $this->mail;
        }

        /**
         * @param mixed $mail
         */
        public function setMail($mail): void
        {
            $this->mail = $mail;
        }

        /**
         * @return mixed
         */
        public function getName()
        {
            return $this->name;
        }

        /**
         * @param mixed $name
         */
        public function setName($name): void
        {
            $this->name = $name;
        }

        /**
         * @return mixed
         */
        public function getFirstName()
        {
            return $this->firstName;
        }

        /**
         * @param mixed $firstName
         */
        public function setFirstName($firstName): void
        {
            $this->firstName = $firstName;
        }

        /**
         * @return mixed
         */
        public function getBirthIsoDate()
        {
            return $this->birthIsoDate;
        }

        /**
         * @param mixed $birthIsoDate
         */
        public function setBirthIsoDate($birthIsoDate): void
        {
            $this->birthIsoDate = $birthIsoDate;
        }

        /**
         * @return mixed
         */
        public function getPwd()
        {
            return $this->pwd;
        }

        /**
         * @param mixed $pwd
         */
        public function setPwd($pwd): void
        {
            $this->pwd = hash('sha512', $pwd);
        }

        /**
         * @return bool
         */
        public function getIsDeleted(): bool
        {
            return $this->isDeleted;
        }

        /**
         * @param bool $isDeleted
         */
        public function setIsDeleted(bool $isDeleted): void
        {
            $this->isDeleted = $isDeleted;
        }

    }
}