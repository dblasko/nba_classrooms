<?php namespace App\Models;
use App\Classes\UserEntity;
use CodeIgniter\Model;
use App\Classes\PromotionEntity;

class PromotionModel extends Model {
    protected $table = 'promotions';
    protected $allowedFields = ['name', 'creationIsoDate', 'isClosedPromotion', 'inviteLink'];

    public function createPromotion($name, $isClosedPromotion, $inviteLink) {
        $promotionData = [
            'name' => $name,
            'creationIsoDate' => date("Y-m-d H:i:s"),
            'isClosedPromotion' => $isClosedPromotion,
            'inviteLink' => $inviteLink,
        ];
        if(!$this->save($promotionData)) return false;
        $query = $this->db->query("SELECT * FROM promotions WHERE inviteLink = ?", array($inviteLink));
        if ($query != null) {
            return $query->getRowArray(0)['id'];
        }
    }

    public function getPromotionsMemberOf($user)
    {
        $promotions = [];
        $query = $this->db->query(
            "SELECT p.id as id, p.name as name, p.creationIsoDate as creationIsoDate, p.isClosedPromotion as isClosedPromotion, p.inviteLink as inviteLink FROM promotionmemberships pm JOIN promotions p ON pm.promotionId = p.id WHERE memberUserMail = ? ORDER BY joinedPromotionIsoDate DESC",
            array($user->getMail()));

        if ($query != null) {
            $promotions = $this->createPromotionsFromQueryRows($query, false);
        }
        return $promotions;
    }

    public function getOpenPromotions() {
        $promotions = [];
        $query = $this->db->query("SELECT * FROM promotions WHERE isClosedPromotion = 0 ORDER BY creationIsoDate DESC");

        if ($query != null) {
            $promotions = $this->createPromotionsFromQueryRows($query, true);
        }
        return $promotions;
    }

    public function getPromotionEntity($promoId) {
        $query = $this->db->query("SELECT * FROM promotions WHERE id = ?", array($promoId));

        if ($query != null) {
            $promotions = $this->createPromotionsFromQueryRows($query, true); // helper function always returns an array
            return (count($promotions) > 0)? $promotions[0] : null;
        }
        return null;
    }

    protected function createPromotionsFromQueryRows($query, $isPromotionDataRequired=false) {
        /*
         * if isPromotionDataRequired is false, the detailed promotion exercises & member data isn't loaded
         * This helps to avoid useless queries in situation where that data isn't needed
         */
        $promotions = [];

        foreach ($query->getResultArray() as $result) {
            // we create the promotion base
            $promotion = new PromotionEntity(
                $result['id'], $result['name'], $result['inviteLink'],
                $result['isClosedPromotion'] == 1, $result['creationIsoDate'],
                null, null, null
            );

            if ($isPromotionDataRequired) {
                $exerciseAssigners = [];
                $exerciseSolvers = [];
                // We iterate through all the members of the promotion, and put them in the right array based on their role
                $promotionMembersWithRoles = $this->db->query("SELECT u.mail as mail, u.name as name, u.firstName as firstName, u.birthIsoDate as birthIsoDate, u.pwd as pwd, u.isDeleted as isDeleted, pm.role as role FROM promotionmemberships pm JOIN users u ON pm.memberUserMail = u.mail WHERE promotionId = ?", $promotion->getId());
                if ($promotionMembersWithRoles != null) {
                    foreach ($promotionMembersWithRoles->getResultArray() as $promotionMemberData) {
                        $promotionMember = new UserEntity(
                            $promotionMemberData['mail'],
                            $promotionMemberData['name'],
                            $promotionMemberData['firstName'],
                            $promotionMemberData['birthIsoDate'],
                            $promotionMemberData['pwd'],
                            $promotionMemberData['isDeleted']
                        );
                        if ($promotionMemberData['role'] == ROLE_TEACHER) $exerciseAssigners[] = $promotionMember; // user is a teacher
                        else $exerciseSolvers[] = $promotionMember; // user is a student of the promotion
                    }
                }

                $promotion->setExerciseAssigners($exerciseAssigners);
                $promotion->setExerciseSolvers($exerciseSolvers);
                // Finally, we generate the exerciseAssignations data
                $exerciseAssignmentModel = new ExerciseAssignmentModel();
                $exerciseAssignations = $exerciseAssignmentModel->getPromotionExerciseAssignationsArray($promotion->getId());

                $promotion->setExerciseAssignations($exerciseAssignations);
            }
            $promotions[] = $promotion;
        }
        return $promotions;
    }

    public function isValidLink($promoLink) {
        $promoData = $this->asArray()
            ->where(['inviteLink' => $promoLink])
            ->first();

        return ($promoData != null);
    }

    public function isValidPromotionId($promoId) {
        $promoData = $this->asArray()
            ->where(['id' => $promoId])
            ->first();

        return ($promoData != null);
    }

    public function isUserMemberOfPromo($userMail, $promoId) {
        $query = $this->db->query("SELECT * FROM promotionmemberships WHERE promotionId = ? AND memberUserMail = ?", array($promoId, $userMail));
        if ($query != null) {
            return ($query->getFirstRow() != null);
        }
        return false;
    }

    public function isUserTeacherOfPromo($userMail, $promoId) {
        $query = $this->db->query("SELECT * FROM promotionmemberships WHERE promotionId = ? AND memberUserMail = ? AND ROLE = '".ROLE_TEACHER."'", array($promoId, $userMail));
        if ($query != null) {
            return ($query->getFirstRow() != null);
        }
        return false;
    }

    public function addUserToPromotion(UserEntity $user, $promoLink) {
        $promoData = $this->asArray()
            ->where(['inviteLink' => $promoLink])
            ->first();

        if ($this->isUserMemberOfPromo($user->getMail(), $promoData['id'])) return false; // already member

        $this->db->query("INSERT INTO promotionmemberships VALUES (?,?,?,?)", array(
            $promoData['id'],
            $user->getMail(),
            date("Y-m-d H:i:s"),
            ROLE_STUDENT, // default when joined
        ));
        return true;
    }

    public function removeUserFromPromotion(UserEntity $user, $promoId) {
        if (!$this->isUserMemberOfPromo($user->getMail(), $promoId)) return false;

        $this->db->query("DELETE FROM promotionmemberships WHERE promotionId = ? AND memberUserMail = ?", array($promoId, $user->getMail()));
        return true;
    }

    public function changeUserRole(UserEntity $user, $promoId, $wantedRole) {
        if (!$this->isUserMemberOfPromo($user->getMail(), $promoId)) return false;

        $this->db->query("UPDATE promotionmemberships SET role = ? WHERE promotionId = ? AND memberUserMail = ?", array($wantedRole, $promoId, $user->getMail()));
        return true;
    }

    public function updatePromo($id, $name, $isClosedPromotion) {
        $result = $this->db->query("UPDATE promotions SET name = ?, isClosedPromotion = ? WHERE id = ?", array($name, $isClosedPromotion, $id));
        return (!$result)? false : true;
    }
}